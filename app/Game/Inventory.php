<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * What the player carries: ammunition, weapons and armor.
 */
final class Inventory
{
    /**
     * Ammunition by the kind
     *
     * @var array<value-of<AmmoType>, int>
     */
    public array $ammo = [AmmoType::BULLETS->value => 50, AmmoType::SHELLS->value => 0];

    /**
     * The weapons that the player has, by the number of the weapon
     *
     * @var array<value-of<Weapon>, bool>
     */
    public array $owned = [
        Weapon::FIST->value => true,
        Weapon::PISTOL->value => true,
        Weapon::SHOTGUN->value => false,
    ];

    /**
     * Points of armor, the type of the armor tells how much of a damage they take
     */
    public int $armor = 0;

    public ArmorType $armorType = ArmorType::NONE;

    public function has(Weapon $weapon): bool
    {
        return $this->owned[$weapon->value] ?? false;
    }

    public function give(Weapon $weapon): void
    {
        $this->owned[$weapon->value] = true;
    }

    /**
     * How much of the ammunition of the kind the player carries.
     *
     * @return int<0, max>
     */
    public function countAmmo(AmmoType $type): int
    {
        return \max(0, $this->ammo[$type->value] ?? 0);
    }

    /**
     * Adds ammunition up to what the player can carry, tells {@see false} when the player carries the most already.
     */
    public function giveAmmo(AmmoType $type, int $amount): bool
    {
        $current = $this->countAmmo($type);

        if ($current >= $type->getCapacity()) {
            return false;
        }

        $this->ammo[$type->value] = \min($type->getCapacity(), $current + $amount);

        return true;
    }

    /**
     * Takes the part of a damage that the armor stops, the armor wears off and is gone when it has no points.
     *
     * @return int the damage that is left for the health
     */
    public function absorb(int $damage): int
    {
        if ($this->armorType === ArmorType::NONE) {
            return $damage;
        }

        $saved = \min(\intdiv($damage, $this->armorType->getAbsorbDivisor()), $this->armor);
        $this->armor -= $saved;

        if ($this->armor <= 0) {
            $this->armorType = ArmorType::NONE;
        }

        return $damage - $saved;
    }
}
