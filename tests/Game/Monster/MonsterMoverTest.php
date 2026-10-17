<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Monster\Direction;
use Serafim\Doom\Game\Monster\MonsterMover;
use Serafim\Doom\Game\Physics\Collider;

#[Group('wad')]
#[CoversClass(MonsterMover::class)]
#[CoversClass(Direction::class)]
final class MonsterMoverTest extends MonsterTestCase
{
    public function testMonsterDirectionIsChosenTowardThePlayerWhenTheWayIsFree(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $zombie = $this->addMonster($world, self::ZOMBIEMAN, 300.0);

        $collider = new Collider($level, $world->state->sectors);
        $mover = new MonsterMover(
            $collider,
            $this->createLines($world),
            $world->player,
            new \Serafim\Doom\Game\Random(3),
            $this->getGameData()->tuning->monster,
        );

        $zombie->ai->moveDirection = Direction::NONE;
        $mover->chooseDirection($zombie, $world->player->x, $world->player->y);

        self::assertNotSame(Direction::NONE, $zombie->ai->moveDirection);

        $toPlayer = \atan2($world->player->y - $zombie->y, $world->player->x - $zombie->x);
        $direction = $zombie->ai->moveDirection->getAngle();
        $difference = \abs(\fmod($direction - $toPlayer + 3 * \M_PI, 2 * \M_PI) - \M_PI);

        self::assertLessThanOrEqual(\M_PI / 2 + 0.01, $difference, 'the monster does not walk away from the player');
    }

    public function testOppositeDirectionIsHalfACircleAway(): void
    {
        self::assertSame(Direction::WEST, Direction::EAST->getOpposite());
        self::assertSame(Direction::NORTH_EAST, Direction::SOUTH_WEST->getOpposite());
        self::assertSame(Direction::NONE, Direction::NONE->getOpposite());
    }
}
