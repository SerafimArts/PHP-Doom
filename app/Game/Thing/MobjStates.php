<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

use Serafim\Doom\Game\Data\StateData;
use Serafim\Doom\Game\Data\StateTable;
use Serafim\Doom\Game\Mobj;

/**
 * Moves things through their states: `P_SetMobjState` and the countdown of `P_MobjThinker`.
 */
final class MobjStates
{
    private ?StateActionHandlerInterface $handler = null;

    public function __construct(
        /** @var StateTable<StateAction> */
        public readonly StateTable $table,
    ) {}

    /**
     * Sets what runs the actions of the states that need the world (walking, attacks, explosions).
     */
    public function setHandler(StateActionHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Puts a thing into a state and runs the action of the state. A state that is {@see null} removes the thing.
     *
     * @param StateData<StateAction>|null $state
     */
    public function set(Mobj $thing, ?StateData $state): void
    {
        while ($state !== null) {
            $thing->state = $state;
            $thing->stateTics = $state->tics;
            $thing->sprite = $state->sprite;
            $thing->frame = $state->frame;
            $thing->isFullBright = $state->isFullBright;

            $this->run($thing, $state->action);

            if ($thing->isRemoved || $thing->stateTics !== 0) {
                return;
            }

            $state = $this->table->getNext($state);
        }

        $thing->state = null;
        $thing->isRemoved = true;
    }

    /**
     * Counts a tic of the state of a thing down and moves the thing to the next state when the tics run out.
     */
    public function tick(Mobj $thing): void
    {
        if ($thing->state === null || $thing->stateTics === -1) {
            return;
        }

        if (--$thing->stateTics === 0) {
            $this->set($thing, $this->table->getNext($thing->state));
        }
    }

    private function run(Mobj $thing, StateAction $action): void
    {
        if ($action === StateAction::NONE) {
            return;
        }

        if ($action === StateAction::FALL) {
            $thing->isSolid = false;

            return;
        }

        $this->handler?->handle($thing, $action);
    }
}
