<?php

declare(strict_types=1);

namespace Local\WAD\Tests;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checks the reader on the shareware `doom1.wad` (md5 f0cefca49926d00903cf57551d901abe).
 */
#[CoversClass(Wad::class)]
#[Group('wad')]
final class SharewareWadTest extends TestCase
{
    private static ?Wad $wad = null;

    private static function getWad(): Wad
    {
        self::skipWithoutShareware();

        return self::$wad ??= Wad::open(self::getShareWarePathname());
    }

    public function testDirectoryHasAllLumps(): void
    {
        self::assertCount(1264, self::getWad()->lumps);
        self::assertSame('IWAD', self::getWad()->kind->value);
    }

    public function testFirstEpisodeHasNineMaps(): void
    {
        self::assertSame(
            ['E1M1', 'E1M2', 'E1M3', 'E1M4', 'E1M5', 'E1M6', 'E1M7', 'E1M8', 'E1M9'],
            self::getWad()->listMaps(),
        );
    }

    public function testFirstLevelHasTheKnownSizes(): void
    {
        $map = self::getWad()->readMap('E1M1');

        self::assertCount(138, $map->things);
        self::assertCount(475, $map->linedefs);
        self::assertCount(648, $map->sidedefs);
        self::assertCount(467, $map->vertexes);
        self::assertCount(732, $map->segs);
        self::assertCount(237, $map->subsectors);
        self::assertCount(236, $map->nodes);
        self::assertCount(85, $map->sectors);
        self::assertSame(904, \strlen($map->reject));
        self::assertNotNull($map->blockmap);
        self::assertSame(36, $map->blockmap->columns);
        self::assertSame(23, $map->blockmap->rows);
        self::assertCount(36 * 23, $map->blockmap->blocks);
    }

    public function testReferencesOfTheFirstLevelStayInsideTheirLumps(): void
    {
        $map = self::getWad()->readMap('E1M1');

        foreach ($map->linedefs as $linedef) {
            self::assertLessThan(\count($map->vertexes), $linedef->startVertex);
            self::assertLessThan(\count($map->vertexes), $linedef->endVertex);
            self::assertLessThan(\count($map->sidedefs), $linedef->rightSidedef);
        }

        foreach ($map->sidedefs as $sidedef) {
            self::assertLessThan(\count($map->sectors), $sidedef->sector);
        }

        foreach ($map->subsectors as $subsector) {
            self::assertLessThanOrEqual(\count($map->segs), $subsector->firstSeg + $subsector->segCount);
        }

        foreach ($map->nodes as $node) {
            foreach ([$node->rightChild, $node->leftChild] as $child) {
                if ($child->isSubsector) {
                    self::assertLessThan(\count($map->subsectors), $child->index);
                } else {
                    self::assertLessThan(\count($map->nodes), $child->index);
                }
            }
        }
    }

    public function testScreenTablesHaveTheirSizes(): void
    {
        self::assertCount(14, self::getWad()->readPalettes());
        self::assertCount(34, self::getWad()->readColormap()->rows);
    }

    public function testEveryFlatOfTheFirstLevelIs64By64(): void
    {
        $wad = self::getWad();

        foreach ($wad->readMap('E1M1')->sectors as $sector) {
            self::assertSame(4096, \strlen($wad->readFlat($sector->floorTexture)->pixels));
            self::assertSame(4096, \strlen($wad->readFlat($sector->ceilingTexture)->pixels));
        }
    }

    public function testTextureListsHaveTheirSizes(): void
    {
        self::assertCount(125, self::getWad()->readTextureDefinitions());
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function solidTextureProvider(): iterable
    {
        foreach (
            [
                'DOOR3', 'LITE3', 'STARTAN3', 'BROWN1', 'DOORSTOP', 'COMPUTE2', 'STEP6', 'BROWN144', 'SUPPORT2',
                'STARG3', 'DOORTRAK', 'SLADWALL', 'TEKWALL4', 'BIGDOOR2', 'STARGR1', 'BROWNGRN', 'NUKE24', 'BROWN96',
                'STARTAN1', 'EXITDOOR', 'SW1STRTN', 'BIGDOOR4', 'EXITSIGN', 'TEKWALL1', 'COMPTALL', 'COMPSPAN',
                'PLANET1', 'COMPTILE', 'STEP1',
            ] as $name
        ) {
            yield $name => [$name];
        }
    }

    #[DataProvider('solidTextureProvider')]
    public function testWallTextureHasNoHolesAndTheDefinedSize(string $name): void
    {
        $wad = self::getWad();
        $definition = $wad->readTextureDefinitions()[$name];

        $texture = $wad->readTexture($name);

        self::assertSame($definition->width, $texture->width);
        self::assertSame($definition->height, $texture->height);
        self::assertTrue($texture->isSolid, $name . ' has transparent pixels');
    }

    public function testWindowTexturesKeepTheTransparencyOfTheirPatches(): void
    {
        self::assertFalse(self::getWad()->readTexture('BRNBIGC')->isSolid);
    }

    public function testSpritesAndInterfaceGraphicsAreDecoded(): void
    {
        $wad = self::getWad();

        $zombie = $wad->readPicture('POSSA1');
        $statusBar = $wad->readPicture('STBAR');

        self::assertSame([41, 55], [$zombie->width, $zombie->height]);
        self::assertFalse($zombie->isSolid);
        self::assertSame([320, 32], [$statusBar->width, $statusBar->height]);
        self::assertTrue($statusBar->isSolid);
        self::assertContains('POSSA1', $wad->listSprites());
        self::assertContains('FLOOR4_8', $wad->listFlats());
    }
}
