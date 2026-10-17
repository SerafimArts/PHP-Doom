<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * A door that opens, waits and closes again. When it meets the player on the way down it opens again.
 */
final class DoorThinker implements Thinker
{
    private DoorPhase $phase = DoorPhase::OPENING;

    private int $countdown = 0;

    private readonly float $topHeight;

    public function __construct(
        private readonly SpecialContext $context,
        private readonly int $sector,
    ) {
        $this->topHeight = $context->getLowestCeilingAround($sector) - $context->specials->doorClearance;
    }

    /**
     * A second use of the door: a closing door opens again, any other one starts to close at once.
     */
    public function use(): void
    {
        if ($this->phase === DoorPhase::CLOSING) {
            $this->phase = DoorPhase::OPENING;

            return;
        }

        $this->phase = DoorPhase::CLOSING;
    }

    /**
     * A monster uses the door: a closing door opens again, a monster never closes a door.
     */
    public function useByMonster(): void
    {
        if ($this->phase === DoorPhase::CLOSING) {
            $this->phase = DoorPhase::OPENING;
        }
    }

    public function think(): bool
    {
        $mover = $this->context->mover;
        $sectors = $this->context->sectors;

        if ($this->phase === DoorPhase::WAITING) {
            if (--$this->countdown <= 0) {
                $this->phase = DoorPhase::CLOSING;
            }

            return true;
        }

        if ($this->phase === DoorPhase::CLOSING) {
            $result = $mover->moveCeiling(
                $this->sector,
                $this->context->specials->doorSpeed,
                (float) $sectors->floorHeights[$this->sector],
                DoorPhase::CLOSING->value,
            );

            if ($result === PlaneResult::REACHED_DESTINATION) {
                $this->context->thinkers->release($this->sector);

                return false;
            }

            if ($result === PlaneResult::CRUSHED) {
                $this->phase = DoorPhase::OPENING;
            }

            return true;
        }

        $result = $mover->moveCeiling(
            $this->sector,
            $this->context->specials->doorSpeed,
            $this->topHeight,
            DoorPhase::OPENING->value,
        );

        if ($result === PlaneResult::REACHED_DESTINATION) {
            $this->phase = DoorPhase::WAITING;
            $this->countdown = $this->context->specials->doorWait;
        }

        return true;
    }
}
