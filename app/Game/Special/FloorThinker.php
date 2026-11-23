<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * Moves a floor to its destination, or lowers a trap floor when no destination is given.
 */
final class FloorThinker implements Thinker
{
    private readonly float $destination;

    private readonly int $direction;

    /**
     * @param (\Closure(): bool)|null $canMove
     */
    public function __construct(
        private readonly SpecialContext $context,
        private readonly int $sector,
        ?float $destination = null,
        private readonly ?\Closure $canMove = null,
    ) {
        $this->direction = $destination !== null && $destination > $context->sectors->floorHeights[$sector] ? 1 : -1;

        if ($destination === null) {
            $destination = $context->getHighestFloorAround($sector);

            if ($destination !== (float) $context->sectors->floorHeights[$sector]) {
                $destination += 8.0;
            }
        }

        $this->destination = $destination;
    }

    public function think(): bool
    {
        if ($this->canMove !== null && !($this->canMove)()) {
            return true;
        }

        $result = $this->context->mover->moveFloor(
            $this->sector,
            $this->context->specials->floorSpeed,
            $this->destination,
            $this->direction,
        );

        if ($result !== PlaneResult::REACHED_DESTINATION) {
            return true;
        }

        $this->context->thinkers->release($this->sector);

        return false;
    }
}
