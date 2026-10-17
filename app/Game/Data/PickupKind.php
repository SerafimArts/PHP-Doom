<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

/**
 * What an item gives to the player (the `GiveXxx` functions of `p_inter.c`).
 */
enum PickupKind: string
{
    case AMMO = 'ammo';
    case HEALTH = 'health';
    case HEALTH_BONUS = 'healthBonus';
    case ARMOR_BONUS = 'armorBonus';
    case ARMOR = 'armor';
    case WEAPON = 'weapon';
}
