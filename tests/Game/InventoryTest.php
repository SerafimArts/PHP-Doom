<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\ArmorType;
use Serafim\Doom\Game\Inventory;
use Serafim\Doom\Game\Weapon;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(Inventory::class)]
final class InventoryTest extends TestCase
{
    public function testPlayerStartsWithTheFistThePistolAndNoShotgun(): void
    {
        $inventory = new Inventory();

        self::assertTrue($inventory->has(Weapon::FIST));
        self::assertTrue($inventory->has(Weapon::PISTOL));
        self::assertFalse($inventory->has(Weapon::SHOTGUN));
        self::assertSame(50, $inventory->countAmmo(AmmoType::BULLETS));
        self::assertSame(0, $inventory->countAmmo(AmmoType::SHELLS));
    }

    public function testAmmunitionStopsAtWhatThePlayerCanCarry(): void
    {
        $inventory = new Inventory();

        self::assertTrue($inventory->giveAmmo(AmmoType::SHELLS, 40));
        self::assertTrue($inventory->giveAmmo(AmmoType::SHELLS, 40));
        self::assertSame(AmmoType::SHELLS->getCapacity(), $inventory->countAmmo(AmmoType::SHELLS));
        self::assertFalse($inventory->giveAmmo(AmmoType::SHELLS, 1), 'a full pouch takes nothing');
    }

    public function testGivingAWeaponMakesThePlayerOwnIt(): void
    {
        $inventory = new Inventory();
        $inventory->give(Weapon::SHOTGUN);

        self::assertTrue($inventory->has(Weapon::SHOTGUN));
    }

    public function testGreenArmorStopsAThirdOfADamageAndWearsOff(): void
    {
        $inventory = new Inventory();
        $inventory->armorType = ArmorType::GREEN;
        $inventory->armor = 100;

        self::assertSame(20, $inventory->absorb(30));
        self::assertSame(90, $inventory->armor);
    }

    public function testBlueArmorStopsHalfOfADamageAndIsGoneWhenItsPointsAreUsed(): void
    {
        $inventory = new Inventory();
        $inventory->armorType = ArmorType::BLUE;
        $inventory->armor = 5;

        self::assertSame(15, $inventory->absorb(20), 'half of 20 is 10 but the armor has 5 points');
        self::assertSame(0, $inventory->armor);
        self::assertSame(ArmorType::NONE, $inventory->armorType);
    }

    public function testWithoutArmorTheWholeDamageGoesToTheHealth(): void
    {
        self::assertSame(17, new Inventory()->absorb(17));
    }
}
