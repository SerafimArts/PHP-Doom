<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

use Serafim\Doom\Game\Mobj;

/**
 * The thing a shot is aimed at and the vertical slope that hits the middle of what is visible of it (`linetarget` and
 * `aimslope` of `P_AimLineAttack`).
 */
final readonly class AimTarget
{
    public function __construct(
        public Mobj $target,
        /**
         * Rise over run
         */
        public float $slope,
    ) {}
}
