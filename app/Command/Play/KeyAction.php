<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Play;

/**
 * What a key press asks for.
 */
enum KeyAction
{
    case USE;

    case WEAPON_1;

    case WEAPON_2;

    case WEAPON_3;

    case TOGGLE_RAYS;

    case TOGGLE_MOUSE;

    case LEAVE;

    /**
     * The number of the weapon that the action takes, {@see null} for an action that is not a weapon.
     *
     * @return int<1, 3>|null
     */
    public function getWeaponSlot(): ?int
    {
        return match ($this) {
            self::WEAPON_1 => 1,
            self::WEAPON_2 => 2,
            self::WEAPON_3 => 3,
            default => null,
        };
    }
}
