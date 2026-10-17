<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

use Serafim\Doom\Game\Thing\MobjStates;
use Serafim\Doom\Game\Thing\ThingSpawner;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;

/**
 * What the systems of a level (combat, monsters, pickups) share: the level and its sectors, the player, the things and
 * the random numbers.
 */
final readonly class GameContext
{
    public function __construct(
        public Level $level,
        public SectorState $sectors,
        public Random $random,
        public MobjStates $states,
        public ThingSpawner $spawner,
        public ThingRegistry $things,
        public Player $player,
        public PlayerStats $stats,
    ) {}
}
