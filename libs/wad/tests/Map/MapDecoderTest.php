<?php

declare(strict_types=1);

namespace Local\WAD\Tests\Map;

use Local\WAD\Exception\LumpNotFoundException;
use Local\WAD\Exception\MalformedLumpException;
use Local\WAD\Map\MapDecoder;
use Local\WAD\Tests\Support\WadBuilder;
use Local\WAD\Tests\TestCase;
use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MapDecoder::class)]
final class MapDecoderTest extends TestCase
{
    public function testThingsUseSignedCoordinates(): void
    {
        $things = MapDecoder::decodeThings(\pack('vvvvv', 0xFFF0, 100, 90, 3004, 7));

        self::assertCount(1, $things);
        self::assertSame(-16, $things[0]->x);
        self::assertSame(100, $things[0]->y);
        self::assertSame(90, $things[0]->angle);
        self::assertSame(3004, $things[0]->type);
        self::assertSame(7, $things[0]->flags);
    }

    public function testLinedefWithoutLeftSideHasNullLeftSidedef(): void
    {
        $linedefs = MapDecoder::decodeLinedefs(
            \pack('v7', 1, 2, 4, 11, 5, 8, 0xFFFF) . \pack('v7', 3, 4, 4, 0, 0, 9, 10),
        );

        self::assertNull($linedefs[0]->leftSidedef);
        self::assertSame(8, $linedefs[0]->rightSidedef);
        self::assertSame(11, $linedefs[0]->special);
        self::assertSame(10, $linedefs[1]->leftSidedef);
    }

    public function testSidedefTextureWithADashMeansNoTexture(): void
    {
        $data = \pack(
            'vv',
            8,
            0xFFFE,
        ) . \str_pad('-', 8, "\0") . \str_pad('stepsx', 8, "\0") . \str_pad('DOOR3', 8, "\0") . \pack('v', 6);

        $sidedef = MapDecoder::decodeSidedefs($data)[0];

        self::assertSame(8, $sidedef->xOffset);
        self::assertSame(-2, $sidedef->yOffset);
        self::assertNull($sidedef->upperTexture);
        self::assertSame('STEPSX', $sidedef->lowerTexture);
        self::assertSame('DOOR3', $sidedef->middleTexture);
        self::assertSame(6, $sidedef->sector);
    }

    public function testSegsKeepDirectionAndSignedOffset(): void
    {
        $segs = MapDecoder::decodeSegs(\pack('v6', 1, 2, 16384, 7, 1, 0xFFFF));

        self::assertTrue($segs[0]->isOpposite);
        self::assertSame(-1, $segs[0]->offset);
        self::assertSame(16384, $segs[0]->angle);
    }

    public function testNodeReadsBoxesAndChildren(): void
    {
        $data = \pack('v14', 0, 0, 64, 0, 10, 0, 0, 10, 0, 10, 0, 10, 0x8003, 5);

        $node = MapDecoder::decodeNodes($data)[0];

        self::assertSame(64, $node->dx);
        self::assertSame(10, $node->rightBox->top);
        self::assertSame(10, $node->rightBox->right);
        self::assertTrue($node->rightChild->isSubsector);
        self::assertSame(3, $node->rightChild->index);
        self::assertFalse($node->leftChild->isSubsector);
        self::assertSame(5, $node->leftChild->index);
    }

    public function testBlockmapSkipsTheStartMarkerOfEveryList(): void
    {
        // 2 columns, 1 row: header, 2 offsets (in shorts: 6 and 10), lists "0, 3, 4, -1" and "0, -1".
        $data = \pack('v*', 0xFF80, 0x0040, 2, 1, 6, 10, 0, 3, 4, 0xFFFF, 0, 0xFFFF);

        $blockmap = MapDecoder::decodeBlockmap($data);

        self::assertSame(-128, $blockmap->originX);
        self::assertSame(64, $blockmap->originY);
        self::assertSame([[3, 4], []], $blockmap->blocks);
    }

    public function testBlockmapListWithoutEndThrowsMalformedLumpException(): void
    {
        $this->expectException(MalformedLumpException::class);
        MapDecoder::decodeBlockmap(\pack('v*', 0, 0, 1, 1, 5, 0, 3));
    }

    public function testRecordsWithABrokenSizeThrowMalformedLumpException(): void
    {
        $this->expectException(MalformedLumpException::class);
        MapDecoder::decodeVertexes('12345');
    }

    public function testMapIsReadFromTheLumpsThatFollowTheMarker(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([
            ['E1M1', ''],
            ['THINGS', \pack('v5', 1, 2, 3, 4, 5)],
            ['LINEDEFS', ''],
            ['SIDEDEFS', ''],
            ['VERTEXES', \pack('v2', 1, 2)],
            ['SEGS', ''],
            ['SSECTORS', ''],
            ['NODES', ''],
            ['SECTORS', ''],
            ['REJECT', 'xyz'],
            ['E1M2', ''],
            ['THINGS', ''],
        ]));

        $map = $wad->readMap('e1m1');

        self::assertSame('E1M1', $map->name);
        self::assertCount(1, $map->things);
        self::assertCount(1, $map->vertexes);
        self::assertSame('xyz', $map->reject);
        self::assertNull($map->blockmap);
    }

    public function testDecodingWithoutARequiredLumpThrowsLumpNotFoundException(): void
    {
        $this->expectException(LumpNotFoundException::class);
        $this->expectExceptionMessage('Level "E1M1" has no SECTORS lump');

        MapDecoder::decode('E1M1', [
            'THINGS' => '',
            'LINEDEFS' => '',
            'SIDEDEFS' => '',
            'VERTEXES' => '',
            'SEGS' => '',
            'SSECTORS' => '',
            'NODES' => '',
        ]);
    }

    public function testMapWithoutRequiredLumpThrowsLumpNotFoundException(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([['E1M1', ''], ['THINGS', '']]));

        $this->expectException(LumpNotFoundException::class);
        $wad->readMap('E1M1');
    }
}
