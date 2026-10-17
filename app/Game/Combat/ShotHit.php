<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

use Serafim\Doom\Game\Mobj;

/**
 * Where a shot ended: on a wall, a floor or a ceiling, or in a thing.
 */
final readonly class ShotHit
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
        /**
         * The thing that was hit, {@see null} when the shot hit the level
         */
        public ?Mobj $thing = null,
    ) {}
}
