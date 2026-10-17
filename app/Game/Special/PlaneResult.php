<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

enum PlaneResult
{
    case MOVED;

    case REACHED_DESTINATION;

    /**
     * The player did not fit and the plane went back
     */
    case CRUSHED;
}
