<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

/**
 * Supported WAD line specials.
 */
enum LineSpecial: int
{
    /**
     * `DR`: a door that opens by use, waits and closes
     */
    case DOOR_OPEN_WAIT_CLOSE = 1;
    case WALK_OPEN_DOOR = 2;
    case WALK_RAISE_TO_CEILING = 5;
    case BUILD_STAIRS = 7;
    case WALK_BUILD_STAIRS = 8;

    /**
     * `S1`: the switch that ends the level
     */
    case EXIT_LEVEL = 11;

    case SWITCH_RAISE_FLOOR = 18;
    case SWITCH_RAISE_PLATFORM = 20;
    case WALK_RAISE_PLATFORM = 22;
    case SWITCH_LOWER_FLOOR = 23;
    case BLUE_DOOR = 26;
    case YELLOW_DOOR = 27;
    case RED_DOOR = 28;
    case OPEN_DOOR = 31;
    case OPEN_BLUE_DOOR = 32;
    case OPEN_RED_DOOR = 33;
    case OPEN_YELLOW_DOOR = 34;

    /**
     * `W1`: lowers the floor of the tagged sectors fast, works once
     */
    case FLOOR_LOWER_TURBO = 36;

    case SECRET_EXIT = 51;
    case SWITCH_LIFT = 62;
    case SWITCH_OPEN_WAIT_CLOSE = 63;
    case SWITCH_LOWER_TURBO = 70;
    case WALK_LOWER_FLOOR = 82;
    case WALK_OPEN_DOOR_REPEAT = 86;

    /**
     * `WR`: a lift of the tagged sectors, works every time
     */
    case LIFT_LOWER_WAIT_RAISE = 88;
    case WALK_OPEN_WAIT_CLOSE = 90;
    case WALK_RAISE_FLOOR = 91;
    case TELEPORT = 97;
    case WALK_LOWER_TURBO_REPEAT = 98;
    case SWITCH_OPEN_DOOR = 103;

    /**
     * A line that acts when the player walks over it.
     */
    public function isWalkTrigger(): bool
    {
        return match ($this) {
            self::FLOOR_LOWER_TURBO, self::LIFT_LOWER_WAIT_RAISE, self::WALK_OPEN_DOOR,
            self::WALK_LOWER_FLOOR, self::WALK_OPEN_WAIT_CLOSE, self::WALK_RAISE_FLOOR, self::TELEPORT,
            self::WALK_RAISE_TO_CEILING, self::WALK_BUILD_STAIRS, self::WALK_RAISE_PLATFORM,
            self::WALK_OPEN_DOOR_REPEAT, self::WALK_LOWER_TURBO_REPEAT => true,
            default => false,
        };
    }

    public function isSectorSwitch(): bool
    {
        return match ($this) {
            self::BUILD_STAIRS, self::SWITCH_RAISE_FLOOR, self::SWITCH_RAISE_PLATFORM,
            self::SWITCH_LOWER_FLOOR, self::SWITCH_LIFT, self::SWITCH_OPEN_DOOR,
            self::SWITCH_OPEN_WAIT_CLOSE, self::SWITCH_LOWER_TURBO => true,
            default => false,
        };
    }

    public function isRepeatableSwitch(): bool
    {
        return match ($this) {
            self::SWITCH_LIFT, self::SWITCH_OPEN_WAIT_CLOSE, self::SWITCH_LOWER_TURBO => true,
            default => false,
        };
    }

    public function isSingleUseWalkTrigger(): bool
    {
        return match ($this) {
            self::FLOOR_LOWER_TURBO, self::WALK_OPEN_DOOR, self::WALK_RAISE_TO_CEILING,
            self::WALK_BUILD_STAIRS, self::WALK_RAISE_PLATFORM => true,
            default => false,
        };
    }
}
