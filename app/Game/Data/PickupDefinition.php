<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\ArmorType;
use Serafim\Doom\Game\Weapon;

/**
 * One kind of item that the player picks up: what it gives and what the message says.
 */
final readonly class PickupDefinition
{
    public function __construct(
        /** @var non-empty-string */
        public string $name,
        public PickupKind $kind,
        /** @var non-empty-string */
        public string $message,
        public ?AmmoType $ammo = null,
        public int $amount = 0,
        /**
         * The health or the armor that the item does not raise the player above
         */
        public int $limit = 0,
        /**
         * A dropped item gives half of the amount
         */
        public bool $isHalvedWhenDropped = false,
        public ?ArmorType $armor = null,
        public ?Weapon $weapon = null,
        /**
         * The health below which the player needs the item and the message tells it
         */
        public int $needBelow = 0,
        /** @var non-empty-string|null */
        public ?string $needMessage = null,
        /**
         * What is said when the weapon is new to the player
         *
         * @var non-empty-string|null
         */
        public ?string $newMessage = null,
    ) {}
}
