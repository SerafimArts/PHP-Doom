<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\ArmorType;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerStats;
use Serafim\Doom\Game\ScreenFeedback;
use Serafim\Doom\Game\Thing\PickupSystem;
use Serafim\Doom\Game\Thing\MobjStates;
use Serafim\Doom\Game\Thing\ThingSpawner;
use Serafim\Doom\Game\Weapon;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(PickupSystem::class)]
#[CoversClass(PlayerStats::class)]
#[CoversClass(ScreenFeedback::class)]
final class PickupSystemTest extends TestCase
{
    public function testClipGivesTenBulletsAndFlashesTheScreen(): void
    {
        $stats = new PlayerStats();

        $remaining = $this->touch(2007, $stats);

        self::assertSame([], $remaining);
        self::assertSame(60, $stats->inventory->ammo['bullets']);
        self::assertSame('Picked up a clip.', $stats->feedback->message);
        self::assertSame(ScreenFeedback::MESSAGE_TICS, $stats->feedback->messageTics);
        self::assertSame(6, $stats->feedback->bonusCount);
        self::assertSame(9, $stats->feedback->getPalette());
    }

    public function testAmmunitionStopsAtTheCapacityAndIsLeftWhenTheStockIsFull(): void
    {
        $stats = new PlayerStats();
        $stats->inventory->ammo['bullets'] = 195;

        self::assertSame([], $this->touch(2007, $stats));
        self::assertSame(AmmoType::BULLETS->getCapacity(), $stats->inventory->ammo['bullets']);

        self::assertCount(1, $this->touch(2007, $stats), 'a full stock leaves the clip where it lies');
        self::assertSame(200, $stats->inventory->ammo['bullets']);
    }

    /**
     * @return iterable<non-empty-string, array{int, string, int, string}>
     */
    public static function ammoDataProvider(): iterable
    {
        yield 'box of bullets' => [2048, 'bullets', 100, 'Picked up a box of bullets.'];
        yield 'shotgun shells' => [2008, 'shells', 4, 'Picked up 4 shotgun shells.'];
        yield 'box of shotgun shells' => [2049, 'shells', 20, 'Picked up a box of shotgun shells.'];
    }

    #[DataProvider('ammoDataProvider')]
    public function testAmmunitionItemsGiveTheirAmount(int $type, string $ammo, int $expected, string $message): void
    {
        $stats = new PlayerStats();

        self::assertSame([], $this->touch($type, $stats));
        self::assertSame($expected, $stats->inventory->ammo[$ammo]);
        self::assertSame($message, $stats->feedback->message);
    }

    public function testStimpackIsLeftAtFullHealth(): void
    {
        $stats = new PlayerStats();

        self::assertCount(1, $this->touch(2011, $stats));
        self::assertNull($stats->feedback->message);

        $stats->health = 50;

        self::assertSame([], $this->touch(2011, $stats));
        self::assertSame(60, $stats->health);
        self::assertSame('Picked up a stimpack.', $stats->feedback->message);
    }

    public function testMedikitHealsAndTellsWhenItWasReallyNeeded(): void
    {
        $stats = new PlayerStats();
        $stats->health = 60;

        $this->touch(2012, $stats);

        self::assertSame(85, $stats->health);
        self::assertSame('Picked up a medikit.', $stats->feedback->message);

        $stats->health = 20;

        $this->touch(2012, $stats);

        self::assertSame(45, $stats->health);
        self::assertSame('Picked up a medikit that you REALLY need!', $stats->feedback->message);

        $stats->health = 90;

        $this->touch(2012, $stats);

        self::assertSame(100, $stats->health, 'health stops at 100');
        self::assertCount(1, $this->touch(2012, $stats));
    }

    public function testHealthBonusGoesAboveTheHundredUpToTwoHundred(): void
    {
        $stats = new PlayerStats();

        self::assertSame([], $this->touch(2014, $stats));
        self::assertSame(101, $stats->health);

        $stats->health = 200;

        self::assertSame([], $this->touch(2014, $stats), 'the bonus is always picked up');
        self::assertSame(200, $stats->health);
        self::assertSame('Picked up a health bonus.', $stats->feedback->message);
    }

    public function testArmorBonusGivesGreenArmorTypeToAPlayerWithoutArmor(): void
    {
        $stats = new PlayerStats();

        $this->touch(2015, $stats);

        self::assertSame(1, $stats->inventory->armor);
        self::assertSame(ArmorType::GREEN, $stats->inventory->armorType);
        self::assertSame('Picked up an armor bonus.', $stats->feedback->message);

        $stats->inventory->armor = 200;
        $stats->inventory->armorType = ArmorType::BLUE;

        $this->touch(2015, $stats);

        self::assertSame(200, $stats->inventory->armor);
        self::assertSame(ArmorType::BLUE, $stats->inventory->armorType, 'the type of the better armor stays');
    }

    public function testGreenAndBlueArmorAreLeftWhenThePlayerHasEnough(): void
    {
        $stats = new PlayerStats();

        self::assertSame([], $this->touch(2018, $stats));
        self::assertSame(
            [100, ArmorType::GREEN, 'Picked up the armor.'],
            [$stats->inventory->armor, $stats->inventory->armorType, $stats->feedback->message],
        );

        self::assertCount(1, $this->touch(2018, $stats));

        self::assertSame([], $this->touch(2019, $stats));
        self::assertSame(
            [200, ArmorType::BLUE, 'Picked up the MegaArmor!'],
            [$stats->inventory->armor, $stats->inventory->armorType, $stats->feedback->message],
        );

        self::assertCount(1, $this->touch(2019, $stats));
    }

    public function testShotgunIsTakenOnceAndThenGivesOnlyShells(): void
    {
        $stats = new PlayerStats();

        self::assertFalse($stats->inventory->owned[Weapon::SHOTGUN->value]);

        self::assertSame([], $this->touch(2001, $stats));
        self::assertTrue($stats->inventory->owned[Weapon::SHOTGUN->value]);
        self::assertSame(Weapon::SHOTGUN, $stats->pendingWeapon);
        self::assertSame(8, $stats->inventory->ammo['shells']);
        self::assertSame('You got the shotgun!', $stats->feedback->message);

        $stats->weapon = Weapon::PISTOL;

        self::assertSame([], $this->touch(2001, $stats));
        self::assertSame(Weapon::PISTOL, $stats->weapon, 'the weapon is not switched a second time');
        self::assertSame(16, $stats->inventory->ammo['shells']);

        $stats->inventory->ammo['shells'] = 50;

        self::assertCount(1, $this->touch(2001, $stats), 'with full shells the second shotgun is left');
    }

    public function testThingIsNotTouchedFromAfarOrFromAboveAndBelow(): void
    {
        $stats = new PlayerStats();
        $pickups = new PickupSystem();

        $thing = $this->createThing(2007, 100.0, 0.0, 0.0);

        self::assertCount(1, $pickups->update(new Player(0.0, 0.0, 0.0, 0.0), $stats, [$thing]), 'too far');
        self::assertCount(
            1,
            $pickups->update(new Player(100.0, 0.0, 40.0, 0.0), $stats, [$thing]),
            'the player is above',
        );
        self::assertCount(
            1,
            $pickups->update(new Player(100.0, 0.0, -100.0, 0.0), $stats, [$thing]),
            'the player is below',
        );
        self::assertCount(0, $pickups->update(new Player(100.0, 0.0, 10.0, 0.0), $stats, [$thing]));
        self::assertTrue($thing->isRemoved);
    }

    public function testScreenTimersRunDownAndTheMessageDisappears(): void
    {
        $stats = new PlayerStats();
        $stats->feedback->say('Hello');
        $stats->feedback->bonusCount = 6;
        $stats->damage(40);

        self::assertSame(5, $stats->feedback->getPalette(), 'the red of the pain is stronger than the gold');

        for ($tic = 0; $tic < 40; ++$tic) {
            $stats->tick();
        }

        self::assertSame(0, $stats->feedback->damageCount);
        self::assertSame(0, $stats->feedback->bonusCount);
        self::assertSame(0, $stats->feedback->getPalette());
        self::assertSame('Hello', $stats->feedback->message);

        for ($tic = 0; $tic < ScreenFeedback::MESSAGE_TICS; ++$tic) {
            $stats->tick();
        }

        self::assertNull($stats->feedback->message);
    }

    public function testArmorTakesAThirdOrAHalfOfTheDamage(): void
    {
        $green = new PlayerStats();
        $green->inventory->armor = 100;
        $green->inventory->armorType = ArmorType::GREEN;
        $green->damage(30);

        self::assertSame([80, 90], [$green->health, $green->inventory->armor]);

        $blue = new PlayerStats();
        $blue->inventory->armor = 100;
        $blue->inventory->armorType = ArmorType::BLUE;
        $blue->damage(30);

        self::assertSame([85, 85], [$blue->health, $blue->inventory->armor]);
    }

    public function testWeaponIsSelectedOnlyWhenOwned(): void
    {
        $stats = new PlayerStats();

        $stats->select(Weapon::SHOTGUN);
        self::assertNull($stats->pendingWeapon);

        $stats->select(Weapon::FIST);
        self::assertSame(Weapon::FIST, $stats->pendingWeapon);
    }

    /**
     * Puts the thing under the player and lets the player pick it up.
     *
     * @return list<Mobj> the things that stay
     */
    private function touch(int $type, PlayerStats $stats): array
    {
        return new PickupSystem()->update(
            new Player(0.0, 0.0, 0.0, 0.0),
            $stats,
            [$this->createThing($type, 0.0, 0.0, 0.0)],
        );
    }

    private function createThing(int $type, float $x, float $y, float $z): Mobj
    {
        $data = $this->getGameData();
        $definition = $data->things->findByType($type);
        \assert($definition !== null);

        return new ThingSpawner(new MobjStates($data->mobjStates), $data->things)->create($definition, $x, $y, $z);
    }
}
