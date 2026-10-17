<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Combat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\ArmorType;
use Serafim\Doom\Game\Combat\DamageSystem;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\PlayerStats;
use Serafim\Doom\Game\Thing\PickupSystem;

#[Group('wad')]
#[CoversClass(DamageSystem::class)]
final class DamageSystemTest extends CombatTestCase
{
    public function testKilledMonsterDiesDropsAnItemAndStopsBlocking(): void
    {
        $data = $this->getGameData();
        $context = $this->createContext($this->getLevel());
        $states = $context->states;
        $spawner = $context->spawner;
        $stats = $context->stats;
        $damage = new DamageSystem($context, $data->tuning->combat);

        $zombie = $spawner->create($data->things->getByName('POSSESSED'), 10.0, 20.0, 0.0);

        self::assertTrue($zombie->isSolid);
        self::assertSame('S_POSS_STND', $zombie->state?->name);

        $damage->damage($zombie, 20);

        self::assertTrue($zombie->isCorpse);
        self::assertFalse($zombie->isShootable);
        self::assertSame(14.0, $zombie->height);
        self::assertSame('S_POSS_DIE1', $zombie->state?->name);
        self::assertSame(1, $stats->kills);
        self::assertCount(1, $context->things);
        $dropped = $context->things->all();
        self::assertSame(2007, $dropped[0]->type);
        self::assertTrue($dropped[0]->isDropped);

        for ($tic = 0; $tic < 40; ++$tic) {
            $states->tick($zombie);
        }

        self::assertSame('S_POSS_DIE5', $zombie->state?->name);
        self::assertFalse($zombie->isSolid, 'A_Fall ran');
        self::assertSame('L', $zombie->frame);
    }

    public function testHardHitTearsAMonsterApart(): void
    {
        $data = $this->getGameData();
        $context = $this->createContext($this->getLevel());
        $states = $context->states;
        $spawner = $context->spawner;
        $damage = new DamageSystem($context, $data->tuning->combat);

        $imp = $spawner->create($data->things->getByName('TROOP'), 0.0, 0.0, 0.0);

        $damage->damage($imp, 61 + 60);

        self::assertSame('S_TROO_XDIE1', $imp->state?->name);
    }

    public function testPainStateAndDeathDoNotApplyToABarrelThatDoesNotFlinch(): void
    {
        $data = $this->getGameData();
        $context = $this->createContext($this->getLevel());
        $states = $context->states;
        $spawner = $context->spawner;
        $damage = new DamageSystem($context, $data->tuning->combat);

        $barrel = $spawner->create($data->things->getByName('BARREL'), 0.0, 0.0, 0.0);

        $damage->damage($barrel, 10);
        self::assertSame(10, $barrel->health);
        self::assertSame('S_BAR1', $barrel->state?->name);

        $damage->damage($barrel, 10);
        self::assertSame('S_BEXP', $barrel->state?->name);
        self::assertTrue($barrel->isSolid, 'the barrel blocks until it is gone');

        for ($tic = 0; $tic < 40 && !$barrel->isRemoved; ++$tic) {
            $states->tick($barrel);
        }

        self::assertTrue($barrel->isRemoved, 'the last state of the explosion removes the barrel');
    }

    public function testDroppedItemsGiveHalfOfWhatTheSameItemsOnTheMapGive(): void
    {
        $stats = new PlayerStats();
        $pickups = new PickupSystem();
        $data = $this->getGameData();

        self::assertSame('Picked up a clip.', $pickups->take($data->pickups->get('CLIP'), $stats, true));
        self::assertSame(55, $stats->inventory->ammo['bullets']);

        $pickups->take($data->pickups->get('SHOTGUN'), $stats, true);
        self::assertSame(4, $stats->inventory->ammo['shells']);
    }

    public function testArmorTakesAThirdOrAHalfOfTheDamage(): void
    {
        $stats = new PlayerStats();
        $stats->inventory->armor = 100;
        $stats->inventory->armorType = ArmorType::GREEN;

        $stats->damage(30);

        self::assertSame(80, $stats->health);
        self::assertSame(90, $stats->inventory->armor);

        $stats->inventory->armorType = ArmorType::BLUE;
        $stats->inventory->armor = 4;
        $stats->damage(20);

        self::assertSame(0, $stats->inventory->armor);
        self::assertSame(ArmorType::NONE, $stats->inventory->armorType, 'the armor is used up');
        self::assertSame(80 - 16, $stats->health);
    }

    public function testDeadPlayerLetsTheViewSinkAndRestartsOnUse(): void
    {
        $world = $this->createWorld($this->getLevel());

        for ($tic = 0; $tic < 20; ++$tic) {
            $world->tick(new PlayerInput());
        }

        $world->stats->health = 0;

        for ($tic = 0; $tic < 50; ++$tic) {
            $world->tick(new PlayerInput(forwardMove: 25, isFiring: true));
        }

        self::assertSame(6.0, $world->player->viewHeight);
        self::assertSame(128.0, $world->weapons->weapon->y, 'the weapon is down');
        self::assertSame(50, $world->stats->inventory->ammo['bullets'], 'the dead cannot shoot');
        self::assertFalse($world->isRestartRequested());

        $world->tick(new PlayerInput(isUsing: true));
        self::assertTrue($world->isRestartRequested());
    }
}
