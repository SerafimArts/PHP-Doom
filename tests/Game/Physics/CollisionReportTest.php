<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Physics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\CollisionReport;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(CollisionReport::class)]
final class CollisionReportTest extends TestCase
{
    public function testReportTellsWhereThePlayerStandsAndWhatBlocksItInEveryDirection(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $collider = new Collider($level, $world->state->sectors, $world->things);

        $report = new CollisionReport($level, $world->state->sectors, $collider)
            ->describe($world->player, $world->things->all(), 7);

        self::assertStringContainsString('tic 7: position', $report);
        self::assertStringContainsString('sector ', $report);
        self::assertStringContainsString('here: free', $report);
        self::assertSame(8, \substr_count($report, "\ndirection "));
        self::assertStringContainsString('free directions: 8 of 8', $report);
    }

    public function testReportNamesTheLineAtThePlayerStandingInAWall(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $collider = new Collider($level, $world->state->sectors, $world->things);
        $player = $world->player;

        foreach ($level->map->linedefs as $linedef) {
            if ($linedef->leftSidedef === null) {
                $from = $level->map->vertexes[$linedef->startVertex];
                $to = $level->map->vertexes[$linedef->endVertex];

                $player->x = ($from->x + $to->x) / 2;
                $player->y = ($from->y + $to->y) / 2;

                break;
            }
        }

        $report = new CollisionReport($level, $world->state->sectors, $collider)
            ->describe($player, $world->things->all(), 1);

        self::assertStringContainsString('here: blocked by line ', $report);
        self::assertStringContainsString('one-sided', $report);
    }
}
