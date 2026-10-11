<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Raytracing;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LevelState;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Mesh\LevelMeshBuilder;
use Serafim\Doom\Render\Raytracing\DynamicSectors;
use Serafim\Doom\Render\Raytracing\DynamicTriangles;
use Serafim\Doom\Render\Raytracing\ShadowTriangles;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(DynamicSectors::class)]
#[CoversClass(DynamicTriangles::class)]
#[CoversClass(ShadowTriangles::class)]
final class DynamicTrianglesTest extends TestCase
{
    private const int DOOR_SECTOR = 76;

    public function testDoorsAndTaggedSectorsOfTheFirstLevelMove(): void
    {
        [$level] = $this->createLevel();

        $sectors = DynamicSectors::find($level);

        foreach ([4, 59, 68, 70, 76, 81] as $sector) {
            self::assertArrayHasKey($sector, $sectors);
        }

        self::assertLessThan(\count($level->map->sectors) / 2, \count($sectors));
    }

    public function testVerticesFollowTheHeightOfTheDoorAndKeepTheirNumber(): void
    {
        [$level, $mesh] = $this->createLevel();

        $dynamic = DynamicTriangles::create($level, $mesh, [self::DOOR_SECTOR => true]);
        $state = LevelState::createFromMap($level->map);

        $closed = $dynamic->packVertices($state->sectors);
        $state->sectors->ceilingHeights[self::DOOR_SECTOR] = 44;
        $open = $dynamic->packVertices($state->sectors);

        self::assertFalse($dynamic->isEmpty());
        self::assertSame($dynamic->vertexCount * 12, \strlen($closed));
        self::assertSame(\strlen($closed), \strlen($open));
        self::assertNotSame($closed, $open);
        self::assertSame($dynamic->triangleCount * 12, \strlen($dynamic->indices));
    }

    public function testStaticTrianglesLeaveOutWhatDependsOnMovingSectors(): void
    {
        [$level, $mesh] = $this->createLevel();

        $everything = ShadowTriangles::createFromLevel($level, $mesh);
        $withoutDoors = ShadowTriangles::createFromLevel($level, $mesh, DynamicSectors::find($level));

        self::assertLessThan($everything->triangleCount, $withoutDoors->triangleCount);
    }

    /**
     * @return array{Level, \Serafim\Doom\Render\Mesh\LevelMesh}
     */
    private function createLevel(): array
    {
        $pathname = \dirname(__DIR__, 3) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        $wad = Wad::open($pathname);
        $level = Level::createFromMap($wad->readMap('E1M1'));
        $atlas = new LevelAtlasFactory($this->getGameData()->animations)->create($wad, $level);

        return [$level, new LevelMeshBuilder()->build($level, $atlas)];
    }
}
