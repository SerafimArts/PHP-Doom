<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * What the player asks for during one tic.
 */
final readonly class PlayerInput
{
    public const int WALK_FORWARD = 25;

    public const int RUN_FORWARD = 50;

    public const int WALK_SIDE = 24;

    public const int RUN_SIDE = 40;

    public function __construct(
        /**
         * Forward speed, negative for backward. Walking is 25, running is 50.
         *
         * @var int<-50, 50>
         */
        public int $forwardMove = 0,
        /**
         * Sideways speed, positive to the right. Walking is 24, running is 40.
         *
         * @var int<-40, 40>
         */
        public int $sideMove = 0,
        /**
         * Counter-clockwise turn in radians
         */
        public float $turn = 0.0,
        /**
         * The player presses the use key: opens a door, presses a switch
         */
        public bool $isUsing = false,
        /**
         * The number of the weapon to take (the key 1 to 3), 0 when the weapon stays
         *
         * @var int<0, 3>
         */
        public int $weaponSlot = 0,
        /**
         * The fire button is down
         */
        public bool $isFiring = false,
    ) {}
}
