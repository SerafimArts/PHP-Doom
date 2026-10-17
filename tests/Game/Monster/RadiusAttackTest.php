<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Monster\RadiusAttack;
use Serafim\Doom\Game\PlayerInput;

#[Group('wad')]
#[CoversClass(RadiusAttack::class)]
final class RadiusAttackTest extends MonsterTestCase
{
    public function testBarrelExplosionHurtsThePlayerAndSetsOffTheBarrelNextToIt(): void
    {
        $world = $this->createWorld($this->getLevel());
        $barrel = $this->addMonster($world, self::BARREL, 110.0);
        $spawner = $this->createSpawner();
        $definition = $this->getGameData()->things->findByType(self::BARREL) ?? throw new \LogicException('no barrel');
        $neighbor = $spawner->create($definition, $barrel->x, $barrel->y + 50.0, $barrel->z);
        $world->things->add($neighbor);

        $world->player->yaw = \atan2($barrel->y - $world->player->y, $barrel->x - $world->player->x);

        for ($tic = 0; $tic < 20 + 14; ++$tic) {
            $world->tick(new PlayerInput());
        }

        for ($tic = 0; $tic < 35 * 4 && !$barrel->isCorpse; ++$tic) {
            $world->tick(new PlayerInput(isFiring: true));
        }

        self::assertTrue($barrel->isCorpse, 'the shots break the barrel');

        $world->weapons->drop();

        for ($tic = 0; $tic < 100; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertLessThan(100, $world->stats->health, 'the blast hurts the player');
        self::assertTrue($neighbor->isCorpse || $neighbor->isRemoved, 'the blast sets off the barrel next to it');
    }
}
