<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * The armor the player wears: `armortype` of `player_t` (0 none, 1 green armor, 2 blue armor).
 */
enum ArmorType: int
{
    case NONE = 0;
    case GREEN = 1;
    case BLUE = 2;

    /**
     * The armor takes `1 / divisor` of every damage (a third for the green armor, a half for the blue one).
     *
     * @return int<0, 3>
     */
    public function getAbsorbDivisor(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::GREEN => 3,
            self::BLUE => 2,
        };
    }

    /**
     * Points a pickup of this armor sets.
     *
     * @return int<0, 200>
     */
    public function getPickupPoints(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::GREEN => 100,
            self::BLUE => 200,
        };
    }
}
