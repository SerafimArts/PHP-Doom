<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

/**
 * The special of a sector that the first level has: `sector_t.special` of linuxdoom, named after the Unofficial Doom
 * Specs.
 */
enum SectorSpecial: int
{
    case LIGHT_BLINK_RANDOM = 1;
    case STROBE_FAST_DAMAGE_20 = 4;
    case DAMAGE_10 = 5;
    case DAMAGE_5 = 7;
    case LIGHT_GLOW = 8;
    case SECRET = 9;
    case STROBE_SLOW_SYNC = 12;
    case DAMAGE_20 = 16;
}
