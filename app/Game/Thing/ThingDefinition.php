<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

use Serafim\Doom\Game\Data\PickupDefinition;
use Serafim\Doom\Game\Data\StateData;

/**
 * A kind of a thing: its size, its behaviour and its states (`mobjinfo_t`).
 */
final readonly class ThingDefinition
{
    public function __construct(
        /**
         * The number of the thing in the map (`doomednum`), a negative number for the things that only the game makes
         */
        public int $type,
        /** @var non-empty-string */
        public string $name,
        public float $radius,
        public float $height,
        /** @var StateData<StateAction> */
        public StateData $spawnState,
        public bool $isSolid = false,
        public ?PickupDefinition $pickup = null,
        public int $health = 0,
        public int $painChance = 0,
        /** @var StateData<StateAction>|null */
        public ?StateData $painState = null,
        /** @var StateData<StateAction>|null */
        public ?StateData $deathState = null,
        /** @var StateData<StateAction>|null */
        public ?StateData $xdeathState = null,
        public bool $isShootable = false,
        public bool $hasBlood = true,
        public bool $isCountedAsKill = false,
        /**
         * The name of the thing that a dead monster drops
         *
         * @var non-empty-string|null
         */
        public ?string $dropName = null,
        public float $speed = 0.0,
        public int $reactionTime = 0,
        /** @var StateData<StateAction>|null */
        public ?StateData $seeState = null,
        /** @var StateData<StateAction>|null */
        public ?StateData $meleeState = null,
        /** @var StateData<StateAction>|null */
        public ?StateData $missileState = null,
        public bool $isMissile = false,
        public int $damage = 0,
        public bool $isCountedAsItem = false,
        /**
         * The thing starts at a random tic of its first state, so that the things of a kind do not move in step
         */
        public bool $isStartRandom = false,
        /**
         * How fast a thing that flies falls, units per tic
         */
        public float $gravity = 0.0,
    ) {}
}
