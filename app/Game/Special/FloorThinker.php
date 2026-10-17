<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * A floor that goes down fast to 8 units above the highest floor around (the trap floors).
 */
final class FloorThinker implements Thinker
{
    private readonly float $destination;

    public function __construct(
        private readonly SpecialContext $context,
        private readonly int $sector,
    ) {
        $destination = $context->getHighestFloorAround($sector);

        if ($destination !== (float) $context->sectors->floorHeights[$sector]) {
            $destination += 8.0;
        }

        $this->destination = $destination;
    }

    public function think(): bool
    {
        $result = $this->context->mover->moveFloor(
            $this->sector,
            $this->context->specials->floorSpeed,
            $this->destination,
            -1,
        );

        if ($result !== PlaneResult::REACHED_DESTINATION) {
            return true;
        }

        $this->context->thinkers->release($this->sector);

        return false;
    }
}
