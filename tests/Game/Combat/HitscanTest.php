<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Combat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Combat\Hitscan;

#[Group('wad')]
#[CoversClass(Hitscan::class)]
final class HitscanTest extends CombatTestCase
{
    public function testAWallStopsTheShot(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $combat = $this->getGameData()->tuning->combat;
        $hitscan = new Hitscan($this->createContext($level, $world->state->sectors), $combat);

        $start = $world->player;
        $hit = $hitscan->shoot($start->x, $start->y, $start->z + 36.0, $start->yaw + \M_PI, 2048.0, 0.0);

        self::assertNotNull($hit);
        self::assertNull($hit->thing);
        self::assertLessThan(2048.0, \hypot($hit->x - $start->x, $hit->y - $start->y));
    }

    public function testAimLooksUpAtATargetThatStandsHigher(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $data = $this->getGameData();
        $spawner = $this->createSpawner();
        $zombie = $spawner->create($data->things->getByName('POSSESSED'), 100.0, 0.0, 40.0);

        $combat = $this->getGameData()->tuning->combat;
        $context = $this->createContext($level, $world->state->sectors);
        $context->things->add($zombie);
        $hitscan = new Hitscan($context, $combat);

        $target = $hitscan->aim(0.0, 0.0, 0.0, 0.0, 1024.0);

        self::assertNotNull($target);
        self::assertSame($zombie, $target->target);
        self::assertGreaterThan(
            0.5,
            $target->slope,
            'the middle of what is visible of the zombie, 40 to 96 units up over 100 units',
        );
        self::assertNull($hitscan->aim(0.0, 0.0, 0.0, \M_PI, 1024.0), 'nothing behind');
        self::assertNull($hitscan->aim(0.0, 0.0, 400.0, 0.0, 1024.0), 'the target is below the autoaim');
    }
}
