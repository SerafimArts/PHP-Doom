<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Combat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Combat\WeaponSystem;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\Weapon;

#[Group('wad')]
#[CoversClass(WeaponSystem::class)]
final class WeaponSystemTest extends CombatTestCase
{
    public function testPistolShotCostsOneBulletAndHurtsWhatIsInFront(): void
    {
        [$world, $zombie] = $this->createDuel();

        $this->fire($world, 12);

        self::assertSame(49, $world->stats->inventory->ammo['bullets']);
        self::assertContains($zombie->health, [5, 10, 15], 'a bullet does 5, 10 or 15 damage');
    }

    public function testShotgunFiresSevenPelletsAtOnceAndKillsAZombieAtPointBlank(): void
    {
        [$world, $zombie] = $this->createDuel(Weapon::SHOTGUN, 60.0);

        $this->fire($world, 12);

        self::assertSame(19, $world->stats->inventory->ammo['shells']);
        self::assertTrue($zombie->isCorpse);
        self::assertSame(1, $world->stats->kills);
    }

    public function testShotIsFiredAfterTheFirstStateOfTheAttackAndTheFlashLightsTheScene(): void
    {
        [$world] = $this->createDuel();

        $input = new PlayerInput(isFiring: true);

        $world->tick($input);
        self::assertSame('S_PISTOL1', $world->weapons->weapon->state?->name);
        self::assertSame(50, $world->stats->inventory->ammo['bullets']);

        for ($tic = 0; $tic < 4; ++$tic) {
            $world->tick($input);
        }

        self::assertSame('S_PISTOL2', $world->weapons->weapon->state?->name);
        self::assertSame(49, $world->stats->inventory->ammo['bullets']);
        self::assertSame('S_PISTOLFLASH', $world->weapons->flash->state?->name);
        self::assertSame(1, $world->stats->extraLight);

        for ($tic = 0; $tic < 7; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertNull($world->weapons->flash->state);
        self::assertSame(0, $world->stats->extraLight);
    }

    public function testHoldingTheButtonRefiresTheWeapon(): void
    {
        [$world] = $this->createDuel();

        $this->fire($world, 45);

        self::assertContains(
            $world->stats->inventory->ammo['bullets'],
            [47, 48],
            'a shot every 19 tics, the first after 4',
        );
    }

    public function testWeaponWithoutAmmunitionChangesToAnotherWeapon(): void
    {
        [$world] = $this->createDuel();

        $world->stats->inventory->ammo['bullets'] = 0;
        $world->stats->inventory->ammo['shells'] = 5;
        $world->stats->inventory->owned[Weapon::SHOTGUN->value] = true;

        $this->fire($world, 3);
        self::assertSame(Weapon::SHOTGUN, $world->stats->pendingWeapon);

        $world->stats->inventory->ammo['shells'] = 0;
        $world->stats->pendingWeapon = null;
        $world->stats->weapon = Weapon::SHOTGUN;
        $world->weapons->bringUp();

        $this->fire($world, 30);
        self::assertSame(Weapon::FIST, $world->stats->weapon);
    }

    public function testFistPunchesOnlyWhatIsWithinReach(): void
    {
        [$world, $zombie] = $this->createDuel(Weapon::FIST, 50.0);
        $this->fire($world, 12);

        self::assertLessThan(20, $zombie->health);
        self::assertSame(50, $world->stats->inventory->ammo['bullets']);

        [$world, $far] = $this->createDuel(Weapon::FIST, 150.0);
        $this->fire($world, 12);

        self::assertSame(20, $far->health);
    }
}
