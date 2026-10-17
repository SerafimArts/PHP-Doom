<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * A lift: goes down to the lowest floor around, waits 3 seconds and comes back up. It goes up again if it meets the
 * player on the way up.
 */
final class PlatformThinker implements Thinker
{
    private const int DOWN = 0;

    private const int WAITING = 1;

    private const int UP = 2;

    private int $status = self::DOWN;

    private int $countdown = 0;

    private readonly float $low;

    private readonly float $high;

    public function __construct(
        private readonly SpecialContext $context,
        private readonly int $sector,
    ) {
        $floor = (float) $context->sectors->floorHeights[$sector];

        $this->low = \min($context->getLowestFloorAround($sector), $floor);
        $this->high = $floor;
    }

    public function think(): bool
    {
        $mover = $this->context->mover;

        if ($this->status === self::DOWN) {
            $result = $mover->moveFloor($this->sector, $this->context->specials->platformSpeed, $this->low, -1);

            if ($result === PlaneResult::REACHED_DESTINATION) {
                $this->status = self::WAITING;
                $this->countdown = $this->context->specials->platformWait;
            }

            return true;
        }

        if ($this->status === self::WAITING) {
            if (--$this->countdown <= 0) {
                $this->status = self::UP;
            }

            return true;
        }

        $result = $mover->moveFloor($this->sector, $this->context->specials->platformSpeed, $this->high, 1);

        if ($result === PlaneResult::CRUSHED) {
            $this->status = self::DOWN;

            return true;
        }

        if ($result === PlaneResult::REACHED_DESTINATION) {
            $this->context->thinkers->release($this->sector);

            return false;
        }

        return true;
    }
}
