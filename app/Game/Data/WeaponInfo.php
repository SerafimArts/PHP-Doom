<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\Combat\PspriteAction;
use Serafim\Doom\Game\Weapon;

/**
 * What a weapon is: its sprite, the ammunition it uses and the states of its animation (`weaponinfo_t`).
 */
final readonly class WeaponInfo
{
    public function __construct(
        public Weapon $weapon,
        /** @var non-empty-string */
        public string $sprite,
        /**
         * {@see null} for a weapon that needs no ammunition
         */
        public ?AmmoType $ammo,
        /** @var StateData<PspriteAction> */
        public StateData $ready,
        /** @var StateData<PspriteAction> */
        public StateData $up,
        /** @var StateData<PspriteAction> */
        public StateData $down,
        /** @var StateData<PspriteAction> */
        public StateData $attack,
        /**
         * The muzzle flash, {@see null} for a weapon without one
         *
         * @var StateData<PspriteAction>|null
         */
        public ?StateData $flash,
    ) {}
}
