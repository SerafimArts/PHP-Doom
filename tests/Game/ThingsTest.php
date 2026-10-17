<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\Thing\ThingAnimator;
use Serafim\Doom\Game\Thing\ThingCatalog;
use Serafim\Doom\Game\Thing\ThingSpawner;
use Serafim\Doom\Game\Weapon;
use Serafim\Doom\Game\World;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(ThingSpawner::class)]
#[CoversClass(ThingAnimator::class)]
#[CoversClass(ThingCatalog::class)]
#[CoversClass(Collider::class)]
#[CoversClass(World::class)]
final class ThingsTest extends TestCase
{

    public function testThingsOfTheLevelAreSpawnedForSkillThree(): void
    {
        $world = $this->createWorld($this->getLevel());

        self::assertSame(
            [
                10 => 2, 12 => 2, 15 => 4, 24 => 7, 35 => 2, 48 => 2, 2001 => 1, 2007 => 2, 2008 => 2, 2011 => 1,
                2012 => 3, 2014 => 12, 2015 => 25, 2018 => 1, 2019 => 1, 2028 => 8, 2035 => 6, 2048 => 1, 2049 => 3,
                3001 => 2, 3004 => 4,
            ],
            $this->countByType($world->things->all()),
        );
    }

    public function testHigherSkillAddsTheMonstersThatLowerSkillLacks(): void
    {
        $world = $this->createWorld($this->getLevel(), [], 1, 4);
        $counts = $this->countByType($world->things->all());

        self::assertSame(16, $counts[9]);
        self::assertSame(9, $counts[3004]);
        self::assertSame(4, $counts[3001]);
    }

    public function testStartsOfThePlayersAndThingsOfMultiplayerAreNotSpawned(): void
    {
        $counts = $this->countByType($this->createWorld($this->getLevel())->things->all());

        foreach ([1, 2, 3, 4, 11, 2003, 2046, 2002] as $type) {
            self::assertArrayNotHasKey($type, $counts);
        }
    }

    public function testThingsStandOnTheFloorAndKeepTheirSprites(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);

        foreach ($world->things as $thing) {
            $floor = $world->state->sectors->floorHeights[$level->findSector((int) $thing->x, (int) $thing->y)];

            self::assertSame((float) $floor, $thing->z);
            self::assertSame($thing->definition?->spawnState->sprite, $thing->sprite);
        }
    }

    public function testCyclesOfTheAnimationFollowTheTimeOfTheWorld(): void
    {
        $world = $this->createWorld($this->getLevel());
        $world->player->x = 1056.0;
        $world->player->y = -3616.0;

        $frames = [];

        for ($tic = 0; $tic < 30; ++$tic) {
            $world->tick(new PlayerInput());

            foreach ($world->things as $thing) {
                $frames[$thing->type][$tic] = [$thing->frame, $thing->isFullBright];
            }
        }

        self::assertSame('A', $frames[2014][0][0]);
        self::assertSame('B', $frames[2014][6][0], 'a health bonus changes its frame every 6 tics');
        self::assertSame('D', $frames[2014][18][0]);
        self::assertSame('A', $frames[2014][24][0]);
        self::assertSame('B', $frames[2035][6][0], 'a barrel changes its frame every 6 tics');
        self::assertSame('B', $frames[3004][10][0], 'a zombieman changes its frame every 10 tics');
        self::assertSame(
            [['A', false], ['B', true], ['A', false]],
            [$frames[2018][4], $frames[2018][5], $frames[2018][12]],
        );
    }

    public function testBarrelStopsThePlayer(): void
    {
        $barrel = $this->findThing(2035);
        $world = $this->createWorld($this->getLevel());
        $world->things->replace([$barrel]);

        $reach = Player::RADIUS + $barrel->radius;
        $isMeaningful = false;

        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$directionX, $directionY]) {
            $world->player->x = $barrel->x + $directionX * 70;
            $world->player->y = $barrel->y + $directionY * 70;
            $world->player->z = $barrel->z;
            $world->player->yaw = \atan2(-$directionY, -$directionX);
            $world->player->momentumX = 0.0;
            $world->player->momentumY = 0.0;

            $closest = 1000.0;

            for ($tic = 0; $tic < 50; ++$tic) {
                $world->tick(new PlayerInput(PlayerInput::WALK_FORWARD));

                $distance = \max(\abs($world->player->x - $barrel->x), \abs($world->player->y - $barrel->y));
                $closest = \min($closest, $distance);

                self::assertGreaterThanOrEqual($reach - 1.0E-6, $distance, 'the player does not go into the barrel');
            }

            if ($closest < $reach + 1.0) {
                $isMeaningful = true;
            }
        }

        self::assertTrue($isMeaningful, 'the player walked up to the barrel from at least one side');
    }

    public function testPlayerWalksThroughAThingThatIsNotSolid(): void
    {
        $pool = $this->findThing(24);
        $world = $this->createWorld($this->getLevel());
        $world->things->replace([$pool]);

        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$directionX, $directionY]) {
            $world->player->x = $pool->x + $directionX * 70;
            $world->player->y = $pool->y + $directionY * 70;
            $world->player->z = $pool->z;
            $world->player->yaw = \atan2(-$directionY, -$directionX);
            $world->player->momentumX = 0.0;
            $world->player->momentumY = 0.0;

            for ($tic = 0; $tic < 30; ++$tic) {
                $world->tick(new PlayerInput(PlayerInput::RUN_FORWARD));
            }

            $past = ($world->player->x - $pool->x) * -$directionX + ($world->player->y - $pool->y) * -$directionY;

            if ($past > 10.0) {
                self::assertCount(1, $world->things, 'a pool of blood is not picked up');

                return;
            }
        }

        self::fail('The player could not walk over the pool from any side');
    }

    public function testWalkingOverAnItemPicksItUp(): void
    {
        $world = $this->createWorld($this->getLevel());
        $item = $this->findThing(2007);
        $world->things->replace([$item]);

        $world->player->x = $item->x;
        $world->player->y = $item->y;
        $world->player->z = $item->z;

        $world->tick(new PlayerInput());

        self::assertCount(0, $world->things);
        self::assertSame(60, $world->stats->inventory->ammo['bullets']);
        self::assertSame('Picked up a clip.', $world->stats->feedback->message);
    }

    public function testWeaponKeysSelectOwnedWeapons(): void
    {
        $world = $this->createWorld($this->getLevel());

        $world->tick(new PlayerInput(weaponSlot: 3));
        self::assertNull($world->stats->pendingWeapon, 'there is no shotgun yet');

        $world->tick(new PlayerInput(weaponSlot: 1));
        self::assertSame(Weapon::FIST, $world->stats->pendingWeapon);

        for ($tic = 0; $tic < 60 && $world->stats->weapon !== Weapon::FIST; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertSame(Weapon::FIST, $world->stats->weapon, 'the pistol goes down and the fist comes up');

        $world->stats->inventory->owned[Weapon::SHOTGUN->value] = true;
        $world->tick(new PlayerInput(weaponSlot: 3));
        self::assertSame(Weapon::SHOTGUN, $world->stats->pendingWeapon);
    }

    /**
     * @param list<Mobj> $things
     *
     * @return array<int, int>
     */
    private function countByType(array $things): array
    {
        $counts = [];

        foreach ($things as $thing) {
            $counts[$thing->type] = ($counts[$thing->type] ?? 0) + 1;
        }

        \ksort($counts);

        return $counts;
    }

    private function findThing(int $type): Mobj
    {
        foreach ($this->createWorld($this->getLevel())->things as $thing) {
            if ($thing->type === $type) {
                return $thing;
            }
        }

        self::fail('The level has no thing of the type ' . $type);
    }
}
