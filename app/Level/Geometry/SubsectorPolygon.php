<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Geometry;

/**
 * The convex floor and ceiling region of a subsector, vertices in counter-clockwise order (y up).
 */
final readonly class SubsectorPolygon
{
    public function __construct(
        /** @var int<0, max> */
        public int $subsector,
        /** @var int<0, max> */
        public int $sector,
        /** @var list<Point> */
        public array $points,
    ) {}

    /**
     * Area of the polygon in square map units.
     */
    public function getArea(): float
    {
        $area = 0.0;
        $count = \count($this->points);

        for ($i = 0; $i < $count; ++$i) {
            $a = $this->points[$i];
            $b = $this->points[($i + 1) % $count];
            $area += $a->x * $b->y - $b->x * $a->y;
        }

        return $area / 2;
    }

    public function getCentroid(): Point
    {
        $x = 0.0;
        $y = 0.0;

        foreach ($this->points as $point) {
            $x += $point->x;
            $y += $point->y;
        }

        $count = \max(1, \count($this->points));

        return new Point($x / $count, $y / $count);
    }
}
