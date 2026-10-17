<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Mobj;

use Serafim\Doom\Game\Mobj;

/**
 * What a missile in flight carries: who fired it and how hard it hits.
 */
final class MissileState
{
    /**
     * It flies until it explodes (`MF_MISSILE`)
     */
    public bool $isFlying = true;

    public function __construct(
        /**
         * What the missile does when it hits is this multiplied by a random number from 1 to 8
         */
        public readonly int $damage,
        /**
         * The monster that fired the missile
         */
        public ?Mobj $owner = null,
    ) {}
}
