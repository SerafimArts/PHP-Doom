<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * The stage of a door: `direction` of `vldoor_t` (1 opening, 0 waiting at the top, -1 closing).
 */
enum DoorPhase: int
{
    case OPENING = 1;
    case WAITING = 0;
    case CLOSING = -1;
}
