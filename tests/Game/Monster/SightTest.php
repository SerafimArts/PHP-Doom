<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Monster\Sight;
use Serafim\Doom\Game\Physics\LineTable;
use Serafim\Doom\Game\Player;

#[Group('wad')]
#[CoversClass(Sight::class)]
final class SightTest extends MonsterTestCase
{
    public function testWallBetweenTwoThingsBlocksTheSight(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $monster = $this->getGameData()->tuning->monster;
        $sight = new Sight($level, $world->state->sectors, new LineTable($level), $monster);

        $start = $world->player;

        self::assertFalse(
            $sight->canSee($start->x, $start->y, $start->z, Player::HEIGHT, 3008.0, -4416.0, 0.0, 56.0),
            'the imp is on the other side of the level',
        );

        [$monster] = $this->findClearSpot($world, 200.0);

        self::assertTrue(
            $sight->canSee($start->x, $start->y, $start->z, Player::HEIGHT, $monster[0], $monster[1], $start->z, 56.0),
        );
    }
}
