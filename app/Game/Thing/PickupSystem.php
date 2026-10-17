<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

use Serafim\Doom\Game\ArmorType;
use Serafim\Doom\Game\Data\PickupDefinition;
use Serafim\Doom\Game\Data\PickupKind;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerStats;

/**
 * The player picks up what the player touches: items go into the stock of the player, and what is not needed (health
 * at full health, ammunition at the full stock) stays where it lies.
 */
final readonly class PickupSystem
{
    private const int BONUS_FLASH = 6;

    /**
     * Picks up the things that the player touches and returns the things that stay.
     *
     * @param list<Mobj> $things
     *
     * @return list<Mobj>
     */
    public function update(Player $player, PlayerStats $stats, array $things): array
    {
        $staying = [];

        foreach ($things as $thing) {
            if ($thing->definition?->pickup !== null && $this->isTouching($player, $thing)) {
                $message = $this->take($thing->definition->pickup, $stats, $thing->isDropped);

                if ($message !== null) {
                    $thing->isRemoved = true;

                    if ($thing->definition->isCountedAsItem) {
                        ++$stats->items;
                    }

                    $stats->feedback->say($message);
                    $stats->feedback->bonusCount += self::BONUS_FLASH;

                    continue;
                }
            }

            $staying[] = $thing;
        }

        return $staying;
    }

    /**
     * Gives an item to the player. Returns the message that tells what was picked up, {@see null} when the player has
     * no use for the item and it stays where it lies.
     *
     * @param bool $isDropped the item fell from a monster, it gives less
     */
    public function take(PickupDefinition $pickup, PlayerStats $stats, bool $isDropped = false): ?string
    {
        return match ($pickup->kind) {
            PickupKind::AMMO => $this->takeAmmo($pickup, $stats, $isDropped),
            PickupKind::HEALTH => $this->takeHealth($pickup, $stats),
            PickupKind::HEALTH_BONUS => $this->takeHealthBonus($pickup, $stats),
            PickupKind::ARMOR_BONUS => $this->takeArmorBonus($pickup, $stats),
            PickupKind::ARMOR => $this->takeArmor($pickup, $stats),
            PickupKind::WEAPON => $this->takeWeapon($pickup, $stats, $isDropped),
        };
    }

    private function isTouching(Player $player, Mobj $thing): bool
    {
        $reach = Player::RADIUS + $thing->radius;

        return \abs($player->x - $thing->x) < $reach
            && \abs($player->y - $thing->y) < $reach
            && $player->z <= $thing->z + $thing->height
            && $player->z + Player::HEIGHT >= $thing->z;
    }

    private function getAmount(PickupDefinition $pickup, bool $isDropped): int
    {
        if ($isDropped && $pickup->isHalvedWhenDropped) {
            return \intdiv($pickup->amount, 2);
        }

        return $pickup->amount;
    }

    private function takeAmmo(PickupDefinition $pickup, PlayerStats $stats, bool $isDropped): ?string
    {
        $amount = $this->getAmount($pickup, $isDropped);

        if ($pickup->ammo === null || !$stats->inventory->giveAmmo($pickup->ammo, $amount)) {
            return null;
        }

        return $pickup->message;
    }

    private function takeHealth(PickupDefinition $pickup, PlayerStats $stats): ?string
    {
        if ($stats->health >= $pickup->limit) {
            return null;
        }

        $isNeeded = $stats->health < $pickup->needBelow;

        $stats->health = \min($pickup->limit, $stats->health + $pickup->amount);

        if ($isNeeded && $pickup->needMessage !== null) {
            return $pickup->needMessage;
        }

        return $pickup->message;
    }

    private function takeHealthBonus(PickupDefinition $pickup, PlayerStats $stats): string
    {
        $stats->health = \min($pickup->limit, $stats->health + $pickup->amount);

        return $pickup->message;
    }

    private function takeArmorBonus(PickupDefinition $pickup, PlayerStats $stats): string
    {
        $stats->inventory->armor = \min($pickup->limit, $stats->inventory->armor + $pickup->amount);

        if ($stats->inventory->armorType === ArmorType::NONE) {
            $stats->inventory->armorType = ArmorType::GREEN;
        }

        return $pickup->message;
    }

    private function takeArmor(PickupDefinition $pickup, PlayerStats $stats): ?string
    {
        $type = $pickup->armor ?? ArmorType::NONE;
        $points = $type->getPickupPoints();

        if ($stats->inventory->armor >= $points) {
            return null;
        }

        $stats->inventory->armor = $points;
        $stats->inventory->armorType = $type;

        return $pickup->message;
    }

    private function takeWeapon(PickupDefinition $pickup, PlayerStats $stats, bool $isDropped): ?string
    {
        if ($pickup->weapon === null) {
            return null;
        }

        $isNew = $stats->inventory->owned[$pickup->weapon->value] !== true;

        if ($isNew) {
            $stats->inventory->owned[$pickup->weapon->value] = true;
            $stats->pendingWeapon = $pickup->weapon;
        }

        $amount = $this->getAmount($pickup, $isDropped);
        $hasAmmo = $pickup->ammo !== null && $stats->inventory->giveAmmo($pickup->ammo, $amount);

        if ($isNew && $pickup->newMessage !== null) {
            return $pickup->newMessage;
        }

        if ($hasAmmo) {
            return $pickup->message;
        }

        return null;
    }
}
