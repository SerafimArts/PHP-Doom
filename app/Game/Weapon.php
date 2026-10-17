<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * The weapons of the first level, the number is the key that selects the weapon. What a weapon uses and shows is in
 * `weapons.json`, see {@see Data\WeaponInfo}.
 */
enum Weapon: int
{
    case FIST = 1;

    case PISTOL = 2;

    case SHOTGUN = 3;
}
