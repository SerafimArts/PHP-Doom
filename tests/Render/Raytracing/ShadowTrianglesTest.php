<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Raytracing;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Mesh\LevelMeshBuilder;
use Serafim\Doom\Render\Raytracing\ShadowTriangles;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(ShadowTriangles::class)]
final class ShadowTrianglesTest extends TestCase
{
    public function testEveryTriangleOfE1M1RefersToExistingVertices(): void
    {
        $pathname = \dirname(__DIR__, 3) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        $wad = Wad::open($pathname);
        $level = Level::createFromMap($wad->readMap('E1M1'));
        $atlas = new LevelAtlasFactory($this->getGameData()->animations)->create($wad, $level);

        $triangles = ShadowTriangles::createFromLevel($level, new LevelMeshBuilder()->build($level, $atlas));

        self::assertGreaterThan(1_000, $triangles->triangleCount);
        self::assertSame($triangles->vertexCount * 12, \strlen($triangles->vertices));
        self::assertSame($triangles->triangleCount * 12, \strlen($triangles->indices));

        $indices = \unpack('V*', $triangles->indices);
        \assert(\is_array($indices));

        self::assertLessThan($triangles->vertexCount, \max($indices));
    }
}
