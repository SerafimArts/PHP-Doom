<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

use Serafim\Doom\Game\Combat\WeaponSystem;
use Serafim\Doom\Game\Monster\MissileSystem;
use Serafim\Doom\Game\Physics\PlayerPhysics;
use Serafim\Doom\Game\Special\LineSpecials;
use Serafim\Doom\Game\Special\SectorSpecials;
use Serafim\Doom\Game\Special\ThinkerList;
use Serafim\Doom\Game\Thing\PickupSystem;
use Serafim\Doom\Game\Thing\ThingAnimator;

/**
 * The systems that a tic of a level runs.
 */
final readonly class GameSystems
{
    public function __construct(
        public PlayerPhysics $physics,
        public WeaponSystem $weapons,
        public PickupSystem $pickups,
        public MissileSystem $missiles,
        public ThingAnimator $animator,
        public ThinkerList $thinkers,
        public LineSpecials $lines,
        public SectorSpecials $sectorSpecials,
    ) {}
}
