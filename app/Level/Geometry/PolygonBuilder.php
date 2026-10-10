<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Geometry;

use Local\WAD\Map\MapData;
use Serafim\Doom\Level\Bsp;
use Serafim\Doom\Level\LevelBounds;

/**
 * Turns the subsectors of a level into convex polygons: the region of a subsector is what is left of the plane after
 * the lines of its ancestors in the BSP and the lines of its own segs have cut it.
 */
final readonly class PolygonBuilder
{
    private const float MARGIN = 128.0;

    private const float EPSILON = 1.0E-6;

    /**
     * @param list<int<0, max>> $subsectorSectors the sector of every subsector
     *
     * @return list<SubsectorPolygon> by the index of a subsector
     */
    public function build(MapData $map, Bsp $bsp, array $subsectorSectors, LevelBounds $bounds): array
    {
        $region = $this->createRegion($bounds);
        $paths = $bsp->getPaths();

        $polygons = [];

        foreach ($map->subsectors as $index => $subsector) {
            $points = $region;

            foreach ($paths[$index] as ['node' => $node, 'isRight' => $isRight]) {
                $points = $this->clip($points, $node->x, $node->y, $node->dx, $node->dy, $isRight);
            }

            for ($i = 0; $i < $subsector->segCount; ++$i) {
                $seg = $map->segs[$subsector->firstSeg + $i];
                $from = $map->vertexes[$seg->startVertex];
                $to = $map->vertexes[$seg->endVertex];

                $points = $this->clip($points, $from->x, $from->y, $to->x - $from->x, $to->y - $from->y, true);
            }

            $polygons[] = new SubsectorPolygon($index, $subsectorSectors[$index], $this->simplify($points));
        }

        return $polygons;
    }

    /**
     * @return list<Point> a rectangle around the level, counter-clockwise
     */
    private function createRegion(LevelBounds $bounds): array
    {
        return [
            new Point($bounds->minX - self::MARGIN, $bounds->minY - self::MARGIN),
            new Point($bounds->maxX + self::MARGIN, $bounds->minY - self::MARGIN),
            new Point($bounds->maxX + self::MARGIN, $bounds->maxY + self::MARGIN),
            new Point($bounds->minX - self::MARGIN, $bounds->maxY + self::MARGIN),
        ];
    }

    /**
     * Keeps the part of a convex polygon that is on the right (or on the left) of the line.
     *
     * @param list<Point> $points
     *
     * @return list<Point>
     */
    private function clip(array $points, int $x, int $y, int $dx, int $dy, bool $keepRight): array
    {
        $sign = 1.0;

        if (!$keepRight) {
            $sign = -1.0;
        }

        $count = \count($points);
        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $current = $points[$i];
            $next = $points[($i + 1) % $count];

            // The signed distance to the directed line is negative on its right side.
            $a = $sign * ($dx * ($current->y - $y) - $dy * ($current->x - $x));
            $b = $sign * ($dx * ($next->y - $y) - $dy * ($next->x - $x));

            if ($a <= self::EPSILON) {
                $result[] = $current;
            }

            if (($a < -self::EPSILON && $b > self::EPSILON) || ($a > self::EPSILON && $b < -self::EPSILON)) {
                $t = $a / ($a - $b);
                $result[] = new Point(
                    $current->x + ($next->x - $current->x) * $t,
                    $current->y + ($next->y - $current->y) * $t,
                );
            }
        }

        return $result;
    }

    /**
     * @param list<Point> $points
     *
     * @return list<Point>
     */
    private function simplify(array $points): array
    {
        $result = [];

        foreach ($points as $point) {
            $previous = $result[\count($result) - 1] ?? null;

            if (
                $previous !== null
                && \abs($previous->x - $point->x) < 1.0E-4
                && \abs($previous->y - $point->y) < 1.0E-4
            ) {
                continue;
            }

            $result[] = $point;
        }

        $first = $result[0] ?? null;
        $last = $result[\count($result) - 1] ?? null;

        if (
            $first !== null
            && $last !== null
            && $first !== $last
            && \abs($first->x - $last->x) < 1.0E-4
            && \abs($first->y - $last->y) < 1.0E-4
        ) {
            \array_pop($result);
        }

        return $result;
    }
}
