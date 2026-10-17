<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

/**
 * The distances that the monsters judge by (`P_AproxDistance`).
 */
final class Distance
{
    private function __construct() {}

    /**
     * The length of a vector, off by up to about 6 percent: the sum of the sides less half of the shorter one.
     */
    public static function approximate(float $dx, float $dy): float
    {
        $dx = \abs($dx);
        $dy = \abs($dy);

        return $dx + $dy - \min($dx, $dy) / 2;
    }
}
