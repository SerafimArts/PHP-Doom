<?php

declare(strict_types=1);

namespace Local\WAD\Tests\Texture;

use Local\WAD\Exception\LumpNotFoundException;
use Local\WAD\Exception\MalformedLumpException;
use Local\WAD\Tests\Support\WadBuilder;
use Local\WAD\Tests\TestCase;
use Local\WAD\Texture\TextureComposer;
use Local\WAD\Texture\TextureDecoder;
use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TextureComposer::class)]
#[CoversClass(TextureDecoder::class)]
final class TextureComposerTest extends TestCase
{
    private static function createWad(string $textures): Wad
    {
        return Wad::createFromString(WadBuilder::build([
            ['PNAMES', WadBuilder::buildPatchNames(['AAA', 'bbb'])],
            ['TEXTURE1', $textures],
            // 2x2 patch of 0x11, solid
            ['AAA', WadBuilder::buildPatch(2, 2, 0, 0, [[[0, "\x11\x11"]], [[0, "\x11\x11"]]])],
            // 2x2 patch with the 0x22 pixel only in the bottom left corner
            ['BBB', WadBuilder::buildPatch(2, 2, 0, 0, [[[1, "\x22"]], []])],
        ]));
    }

    public function testDefinitionsKeepTheOrderAndTheNamesOfPatches(): void
    {
        $wad = self::createWad(WadBuilder::buildTextures([
            ['WALL', 4, 2, [[0, 0, 0], [2, 0, 1]]],
        ]));

        $definition = $wad->readTextureDefinitions()['WALL'];

        self::assertSame(4, $definition->width);
        self::assertSame(2, $definition->height);
        self::assertSame(
            ['AAA', 'BBB'],
            \array_map(static fn($patch): string => $patch->patchName, $definition->patches),
        );
        self::assertSame(2, $definition->patches[1]->originX);
    }

    public function testLaterPatchesCoverEarlierOnesOnlyWhereTheyAreOpaque(): void
    {
        $wad = self::createWad(WadBuilder::buildTextures([
            ['WALL', 2, 2, [[0, 0, 0], [0, 0, 1]]],
        ]));

        $texture = $wad->readTexture('wall');

        self::assertSame("\x11\x11\x22\x11", $texture->pixels);
        self::assertTrue($texture->isSolid);
    }

    public function testPatchesAreClippedByTheTextureBounds(): void
    {
        $wad = self::createWad(WadBuilder::buildTextures([
            ['WIDE', 3, 2, [[-1, 0, 0], [2, 1, 0]]],
        ]));

        $texture = $wad->readTexture('WIDE');

        // The first patch is shifted by -1 and only its right column is inside. The second patch starts
        // at column 2 row 1 and is cut by the right and bottom edges.
        self::assertSame(3, $texture->width);
        self::assertSame("\x11\x00\x00\x11\x00\x11", $texture->pixels);
        self::assertSame("\x01\x00\x00\x01\x00\x01", $texture->mask);
        self::assertFalse($texture->isSolid);
    }

    public function testUnknownTextureThrowsLumpNotFoundException(): void
    {
        $wad = self::createWad(WadBuilder::buildTextures([]));

        $this->expectException(LumpNotFoundException::class);
        $wad->readTexture('MISSING');
    }

    public function testPatchIndexOutOfPnamesThrowsMalformedLumpException(): void
    {
        $wad = self::createWad(WadBuilder::buildTextures([['BAD', 2, 2, [[0, 0, 9]]]]));

        $this->expectException(MalformedLumpException::class);
        $wad->readTextureDefinitions();
    }

    public function testMissingPatchLumpThrowsLumpNotFoundException(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([
            ['PNAMES', WadBuilder::buildPatchNames(['GONE'])],
            ['TEXTURE1', WadBuilder::buildTextures([['T', 1, 1, [[0, 0, 0]]]])],
        ]));

        $this->expectException(LumpNotFoundException::class);
        $wad->readTexture('T');
    }
}
