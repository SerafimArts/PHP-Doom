<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Monster\Distance;
use Serafim\Doom\Game\Monster\MonsterBrain;
use Serafim\Doom\Game\PlayerInput;

#[Group('wad')]
#[CoversClass(MonsterBrain::class)]
final class MonsterBrainTest extends MonsterTestCase
{
    public function testZombiemanThatSeesThePlayerWalksUpAndShoots(): void
    {
        $world = $this->createWorld($this->getLevel());
        $zombie = $this->addMonster($world, self::ZOMBIEMAN, 400.0);

        $closest = Distance::approximate($zombie->x - $world->player->x, $zombie->y - $world->player->y);

        for ($tic = 0; $tic < 35 * 15 && !$zombie->isCorpse; ++$tic) {
            $world->tick(new PlayerInput());
            $closest = \min(
                $closest,
                Distance::approximate($zombie->x - $world->player->x, $zombie->y - $world->player->y),
            );
        }

        self::assertLessThan(400.0, $closest, 'the zombieman comes closer');
        self::assertLessThan(100, $world->stats->health, 'its bullets hit the player');
        self::assertGreaterThanOrEqual(100 - 15 * 10, $world->stats->health);
    }

    public function testImpComesToTheClawsRangeOrThrowsFireballs(): void
    {
        $world = $this->createWorld($this->getLevel());
        $imp = $this->addMonster($world, self::IMP, 300.0);

        $closest = 1000.0;
        $isFireballSeen = false;

        for ($tic = 0; $tic < 35 * 15; ++$tic) {
            $world->tick(new PlayerInput());

            $closest = \min($closest, Distance::approximate($imp->x - $world->player->x, $imp->y - $world->player->y));

            foreach ($world->things as $thing) {
                if ($thing->definition?->name === 'TROOP_SHOT') {
                    $isFireballSeen = true;
                }
            }
        }

        self::assertTrue($isFireballSeen, 'the imp throws a fireball');
        self::assertLessThan(60.0, $closest, 'the imp comes to the claws range');
        self::assertLessThan(100, $world->stats->health);
    }

    public function testMonsterDoesNotWakeBySightWhenThePlayerIsBehindIt(): void
    {
        $world = $this->createWorld($this->getLevel());
        $zombie = $this->addMonster($world, self::ZOMBIEMAN, 300.0, true);

        $zombie->angle = \atan2($zombie->y - $world->player->y, $zombie->x - $world->player->x);

        for ($tic = 0; $tic < 70; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertContains(
            $zombie->state?->name,
            ['S_POSS_STND', 'S_POSS_STND2'],
            'it looks away from the player',
        );
        self::assertFalse($zombie->ai->hasTarget);
    }

    public function testShotWakesAMonsterThatCannotSeeThePlayer(): void
    {
        $world = $this->createWorld($this->getLevel());
        $zombie = $this->addMonster($world, self::ZOMBIEMAN, 300.0, true);

        $zombie->angle = \atan2($zombie->y - $world->player->y, $zombie->x - $world->player->x);

        for ($tic = 0; $tic < 40; ++$tic) {
            $world->tick(new PlayerInput(isFiring: true));
        }

        self::assertTrue($zombie->ai->hasTarget || $zombie->isCorpse, 'the noise of the shots wakes it');
    }

    public function testAmbushMonsterThatSeesThePlayerWakesByTheNoise(): void
    {
        $world = $this->createWorld($this->getLevel());
        $zombie = $this->addMonster($world, self::ZOMBIEMAN, 300.0, true);

        $zombie->ai->isAmbush = true;
        $zombie->angle = \atan2($zombie->y - $world->player->y, $zombie->x - $world->player->x);

        for ($tic = 0; $tic < 40; ++$tic) {
            $world->tick(new PlayerInput(isFiring: true));
        }

        self::assertTrue(
            $zombie->ai->hasTarget || $zombie->isCorpse,
            'an ambush monster wakes by the noise when it sees the player',
        );
    }
}
