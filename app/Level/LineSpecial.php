<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

/**
 * The special of a line that the first level has: `line_t.special` of linuxdoom, named after the Unofficial Doom
 * Specs.
 */
enum LineSpecial: int
{
    /**
     * `DR`: a door that opens by use, waits and closes
     */
    case DOOR_OPEN_WAIT_CLOSE = 1;

    /**
     * `S1`: the switch that ends the level
     */
    case EXIT_LEVEL = 11;

    /**
     * `W1`: lowers the floor of the tagged sectors fast, works once
     */
    case FLOOR_LOWER_TURBO = 36;

    /**
     * `WR`: a lift of the tagged sectors, works every time
     */
    case LIFT_LOWER_WAIT_RAISE = 88;

    /**
     * A line that acts when the player walks over it.
     */
    public function isWalkTrigger(): bool
    {
        return match ($this) {
            self::FLOOR_LOWER_TURBO, self::LIFT_LOWER_WAIT_RAISE => true,
            default => false,
        };
    }
}
