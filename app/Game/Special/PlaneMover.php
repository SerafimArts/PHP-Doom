<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Level\SectorState;

/**
 * Moves the floor or the ceiling of a sector toward a height. A move that would leave no room for the player is taken
 * back.
 */
final readonly class PlaneMover
{
    public function __construct(
        private SectorState $sectors,
        private Collider $collider,
        private Player $player,
    ) {}

    /**
     * @param int $direction 1 up, -1 down
     */
    public function moveFloor(int $sector, float $speed, float $destination, int $direction): PlaneResult
    {
        $before = $this->sectors->floorHeights[$sector];
        $reached = false;

        $after = $before + $direction * $speed;

        if (($direction > 0 && $after > $destination) || ($direction < 0 && $after < $destination)) {
            $after = $destination;
            $reached = true;
        }

        $this->sectors->floorHeights[$sector] = $after;

        if (!$this->doesPlayerFit()) {
            $this->sectors->floorHeights[$sector] = $before;

            return PlaneResult::CRUSHED;
        }

        if ($reached) {
            return PlaneResult::REACHED_DESTINATION;
        }

        return PlaneResult::MOVED;
    }

    /**
     * @param int $direction 1 up, -1 down
     */
    public function moveCeiling(int $sector, float $speed, float $destination, int $direction): PlaneResult
    {
        $before = $this->sectors->ceilingHeights[$sector];
        $reached = false;

        $after = $before + $direction * $speed;

        if (($direction > 0 && $after > $destination) || ($direction < 0 && $after < $destination)) {
            $after = $destination;
            $reached = true;
        }

        $this->sectors->ceilingHeights[$sector] = $after;

        if (!$this->doesPlayerFit()) {
            $this->sectors->ceilingHeights[$sector] = $before;

            return PlaneResult::CRUSHED;
        }

        if ($reached) {
            return PlaneResult::REACHED_DESTINATION;
        }

        return PlaneResult::MOVED;
    }

    private function doesPlayerFit(): bool
    {
        $check = $this->collider->check($this->player->x, $this->player->y, $this->player->z);

        return $check->ceilingZ - $check->floorZ >= Player::HEIGHT;
    }
}
