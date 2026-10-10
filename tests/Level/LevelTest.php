<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Level;

use Local\WAD\Map\MapData;
use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Level\Geometry\PolygonBuilder;
use Serafim\Doom\Level\Geometry\SubsectorPolygon;
use Serafim\Doom\Level\Geometry\WallBuilder;
use Serafim\Doom\Level\Geometry\WallPartType;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(Level::class)]
#[CoversClass(PolygonBuilder::class)]
#[CoversClass(WallBuilder::class)]
final class LevelTest extends TestCase
{


    public function testEverySubsectorGetsADegenerateFreeConvexPolygon(): void
    {
        foreach ($this->getLevel()->geometry->polygons as $polygon) {
            self::assertGreaterThanOrEqual(3, \count($polygon->points), 'subsector ' . $polygon->subsector);
            self::assertGreaterThan(0.0, $polygon->getArea(), 'subsector ' . $polygon->subsector);
            self::assertTrue($this->isConvex($polygon), 'subsector ' . $polygon->subsector);
        }

        self::assertCount(237, $this->getLevel()->geometry->polygons);
    }

    public function testBspFindsTheSubsectorOfEveryPolygonByItsCentroid(): void
    {
        $level = $this->getLevel();

        foreach ($level->geometry->polygons as $polygon) {
            $centroid = $polygon->getCentroid();

            self::assertSame(
                $polygon->subsector,
                $level->findSubsector((int) \round($centroid->x), (int) \round($centroid->y)),
            );
        }
    }

    public function testPolygonAreasOfASectorMatchTheAreaOfItsLinedefLoopsWithinRounding(): void
    {
        $map = $this->getLevel()->map;
        $loops = \array_fill(0, \count($map->sectors), 0.0);

        foreach ($map->linedefs as $linedef) {
            $a = $map->vertexes[$linedef->startVertex];
            $b = $map->vertexes[$linedef->endVertex];
            $cross = ($a->x * $b->y - $b->x * $a->y) / 2;

            $loops[$map->sidedefs[$linedef->rightSidedef]->sector] -= $cross;

            if ($linedef->leftSidedef !== null) {
                $loops[$map->sidedefs[$linedef->leftSidedef]->sector] += $cross;
            }
        }

        $polygons = \array_fill(0, \count($map->sectors), 0.0);

        foreach ($this->getLevel()->geometry->polygons as $polygon) {
            $polygons[$polygon->sector] += $polygon->getArea();
        }

        foreach ($loops as $sector => $area) {
            self::assertGreaterThan(0.0, $polygons[$sector], 'sector ' . $sector . ' has no floor');
            // The node builder rounds the points where it splits walls to whole units.
            self::assertEqualsWithDelta($area, $polygons[$sector], \max(256.0, $area * 0.005), 'sector ' . $sector);
        }
    }

    public function testPlayerStartIsInsideItsSubsectorPolygon(): void
    {
        $level = $this->getLevel();

        foreach ($this->getLevel()->map->things as $thing) {
            if ($thing->type !== 1) {
                continue;
            }

            $polygon = $level->geometry->polygons[$level->findSubsector($thing->x, $thing->y)];

            self::assertTrue($this->contains($polygon, $thing->x, $thing->y));
            self::assertSame($polygon->sector, $level->findSector($thing->x, $thing->y));

            return;
        }

        self::fail('The level has no player start');
    }

    public function testWallPartsFollowTheTexturesOfTheSegSides(): void
    {
        $walls = $this->getLevel()->geometry->walls;
        $types = ['UPPER' => 0, 'LOWER' => 0, 'MIDDLE' => 0];

        foreach ($walls as $wall) {
            ++$types[$wall->type->name];

            self::assertGreaterThan(0.0, $wall->getLength());

            if ($wall->type !== WallPartType::MIDDLE) {
                self::assertNotNull($wall->backSector, 'upper and lower parts exist only in two-sided walls');
            }
        }

        self::assertGreaterThan(0, $types['MIDDLE']);
        self::assertGreaterThan(0, $types['UPPER']);
        self::assertGreaterThan(0, $types['LOWER']);
        self::assertCount(704, $walls);
    }

    public function testScrollingWallsAreMarked(): void
    {
        $scrolling = \array_filter(
            $this->getLevel()->geometry->walls,
            static fn($wall): bool => $wall->isScrolling,
        );

        self::assertNotSame([], $scrolling);
    }

    private function isConvex(SubsectorPolygon $polygon): bool
    {
        $count = \count($polygon->points);

        for ($i = 0; $i < $count; ++$i) {
            $a = $polygon->points[$i];
            $b = $polygon->points[($i + 1) % $count];
            $c = $polygon->points[($i + 2) % $count];

            if (($b->x - $a->x) * ($c->y - $b->y) - ($b->y - $a->y) * ($c->x - $b->x) < -1.0E-6) {
                return false;
            }
        }

        return true;
    }

    private function contains(SubsectorPolygon $polygon, int $x, int $y): bool
    {
        $count = \count($polygon->points);

        for ($i = 0; $i < $count; ++$i) {
            $a = $polygon->points[$i];
            $b = $polygon->points[($i + 1) % $count];

            if (($b->x - $a->x) * ($y - $a->y) - ($b->y - $a->y) * ($x - $a->x) < -1.0E-6) {
                return false;
            }
        }

        return true;
    }
}
