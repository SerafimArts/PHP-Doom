<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

use Serafim\Doom\Game\Mobj;

/**
 * Does what the actions of the states ask for: the walking, the attacks and the explosion.
 */
interface StateActionHandlerInterface
{
    public function handle(Mobj $thing, StateAction $action): void;
}
