<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Geometry;

/**
 * Which texture of a sidedef a wall part shows. The value is the number of the part in the wall data of the shaders.
 */
enum WallPartType: int
{
    /**
     * Above an opening, between the ceilings of the two sectors (the only part of a door that is visible when the
     * door is open and its sector ceiling is higher than the next one).
     */
    case UPPER = 0;

    /**
     * Below an opening, between the floors of the two sectors.
     */
    case LOWER = 1;

    /**
     * The whole height of a one-sided wall, or a masked texture inside the opening of a two-sided one.
     */
    case MIDDLE = 2;
}
