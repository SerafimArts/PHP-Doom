<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Hud;

/**
 * What the screen of the player shows about the player: the numbers of the status bar, the message and the weapon in
 * the hands.
 */
final readonly class HudState
{
    public function __construct(
        public int $health,
        public int $armor,
        /**
         * Ammunition of the weapon in the hands, {@see null} for a weapon that needs none
         */
        public ?int $weaponAmmo,
        public int $bullets,
        public int $bulletsCapacity,
        public int $shells,
        public int $shellsCapacity,
        /**
         * The weapons that the player has, by the number of the weapon (1 is the fist)
         *
         * @var array<int, bool>
         */
        public array $ownedWeapons,
        /**
         * What the screen says, {@see null} when there is nothing to say
         */
        public ?string $message,
        /**
         * The screen is red while the player is hurt: how much hurt remains
         */
        public int $damageCount,
        public bool $isDead,
        /**
         * Time in tics, the face looks around with it
         */
        public int $tic,
        /**
         * The light level of the sector the player stands in
         */
        public int $light,
        /**
         * Light that a muzzle flash adds to the whole scene, in steps of the colormap
         */
        public int $extraLight,
        public ?PspriteView $weapon = null,
        public ?PspriteView $flash = null,
    ) {}
}
