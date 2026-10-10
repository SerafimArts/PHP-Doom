<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Geometry;

/**
 * A point of the level plane in map units.
 */
final readonly class Point
{
    public function __construct(
        public float $x,
        public float $y,
    ) {}
}
