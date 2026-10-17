<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

use Serafim\Doom\Game\Data\SpecialTable;
use Serafim\Doom\Game\Random;
use Serafim\Doom\Level\Data\AnimationTable;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;

/**
 * What the thinkers of the sectors work with, and the questions they ask about the sectors around.
 */
final readonly class SpecialContext
{
    public function __construct(
        public Level $level,
        public SectorState $sectors,
        public PlaneMover $mover,
        public ThinkerList $thinkers,
        public Random $random,
        public SpecialTable $specials,
        public AnimationTable $animations,
    ) {}

    public function getLowestCeilingAround(int $sector): float
    {
        $lowest = \PHP_INT_MAX;

        foreach ($this->level->neighbors[$sector] as $neighbor) {
            $lowest = \min($lowest, $this->sectors->ceilingHeights[$neighbor]);
        }

        return (float) $lowest;
    }

    public function getHighestFloorAround(int $sector): float
    {
        $highest = -500.0;

        foreach ($this->level->neighbors[$sector] as $neighbor) {
            $highest = \max($highest, $this->sectors->floorHeights[$neighbor]);
        }

        return $highest;
    }

    /**
     * The lowest floor around the sector, or the floor of the sector itself when it is lower.
     */
    public function getLowestFloorAround(int $sector): float
    {
        $lowest = $this->sectors->floorHeights[$sector];

        foreach ($this->level->neighbors[$sector] as $neighbor) {
            $lowest = \min($lowest, $this->sectors->floorHeights[$neighbor]);
        }

        return (float) $lowest;
    }

    /**
     * The lowest light around the sector, or the given light when nothing around is darker.
     */
    public function getMinLightAround(int $sector, int $light): int
    {
        foreach ($this->level->neighbors[$sector] as $neighbor) {
            $light = \min($light, $this->sectors->lights[$neighbor]);
        }

        return $light;
    }
}
