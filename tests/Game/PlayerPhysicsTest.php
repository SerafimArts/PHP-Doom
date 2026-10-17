<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game;

use Local\WAD\Map\MapData;
use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\PlayerPhysics;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\World;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LinedefFlag;
use Serafim\Doom\Level\SectorState;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(PlayerPhysics::class)]
#[CoversClass(Collider::class)]
#[CoversClass(World::class)]
final class PlayerPhysicsTest extends TestCase
{

    public function testPlayerStandsStillOnTheFloorWithoutInput(): void
    {
        $world = $this->createWorld($this->getLevel());
        $start = [$world->player->x, $world->player->y];

        for ($tic = 0; $tic < 100; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertSame($start, [$world->player->x, $world->player->y]);
        self::assertSame($world->player->floorZ, $world->player->z);
    }

    public function testWalkingAcceleratesToTheWalkingSpeedAndStopsByFriction(): void
    {
        $world = $this->createWorld($this->getLevel());

        for ($tic = 0; $tic < 25; ++$tic) {
            $world->tick(new PlayerInput(PlayerInput::WALK_FORWARD));
        }

        $speed = \hypot($world->player->momentumX, $world->player->momentumY);

        self::assertGreaterThan(6.0, $speed);
        self::assertLessThan(8.4, $speed);

        for ($tic = 0; $tic < 80; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertSame(0.0, \hypot($world->player->momentumX, $world->player->momentumY));
    }

    public function testRunningIsFasterThanWalking(): void
    {
        $level = $this->getLevel();
        $walker = $this->createWorld($level);
        $runner = $this->createWorld($level);

        for ($tic = 0; $tic < 20; ++$tic) {
            $walker->tick(new PlayerInput(PlayerInput::WALK_FORWARD));
            $runner->tick(new PlayerInput(PlayerInput::RUN_FORWARD));
        }

        self::assertGreaterThan(
            \hypot($walker->player->momentumX, $walker->player->momentumY) * 1.5,
            \hypot($runner->player->momentumX, $runner->player->momentumY),
        );
    }

    public function testRandomWalkNeverGoesThroughWallsOrOutOfTheSpace(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $walls = $this->findOneSidedLines($level->map);

        \mt_srand(7);
        $turn = 0.0;

        for ($tic = 0; $tic < 4000; ++$tic) {
            if ($tic % 40 === 0) {
                $turn = \mt_rand(-100, 100) / 100 * 0.8;
            }

            $world->tick(new PlayerInput(
                \mt_rand(0, 4) > 0 ? PlayerInput::RUN_FORWARD : -PlayerInput::WALK_FORWARD,
                \mt_rand(-1, 1) * PlayerInput::RUN_SIDE,
                $tic % 40 < 3 ? $turn : 0.0,
            ));

            $player = $world->player;

            self::assertGreaterThanOrEqual($player->floorZ, $player->z, 'tic ' . $tic);
            self::assertLessThanOrEqual($player->ceilingZ, $player->z + Player::HEIGHT + 1.0E-6, 'tic ' . $tic);

            foreach ($walls as [$x1, $y1, $x2, $y2]) {
                // The corner of the square player can come as close as the radius divided by the root of two.
                self::assertGreaterThan(
                    11.0,
                    $this->getDistance($player->x, $player->y, $x1, $y1, $x2, $y2),
                    'tic ' . $tic,
                );
            }
        }
    }

    public function testPlayerSlidesAlongAWallInsteadOfStoppingAtIt(): void
    {
        $level = $this->getLevel();
        $sectors = SectorState::createFromMap($level->map);
        $collider = new Collider($level, $sectors);

        foreach ($this->findOneSidedLines($level->map) as [$x1, $y1, $x2, $y2]) {
            $length = \hypot($x2 - $x1, $y2 - $y1);

            if ($length < 300) {
                continue;
            }

            $directionX = ($x2 - $x1) / $length;
            $directionY = ($y2 - $y1) / $length;
            $normalX = $directionY;
            $normalY = -$directionX;
            $startX = ($x1 + $x2) / 2 - $directionX * 100 + $normalX * 24;
            $startY = ($y1 + $y2) / 2 - $directionY * 100 + $normalY * 24;

            if (!$this->isRunwayFree($collider, $startX, $startY, $directionX, $directionY, 200)) {
                continue;
            }

            $floor = $collider->check($startX, $startY, 0.0)->floorZ;
            $player = new Player($startX, $startY, $floor, \atan2($directionY, $directionX) - 0.5);
            $physics = new PlayerPhysics($collider, $this->getGameData()->tuning->physics);

            for ($tic = 0; $tic < 25; ++$tic) {
                $physics->tic($player, new PlayerInput(PlayerInput::WALK_FORWARD));
            }

            $along = ($player->x - $startX) * $directionX + ($player->y - $startY) * $directionY;
            $distance = ($player->x - $x1) * $normalX + ($player->y - $y1) * $normalY;

            self::assertGreaterThan(60.0, $along, 'the player keeps going along the wall');
            self::assertGreaterThan(11.0, $distance, 'the player does not enter the wall');

            return;
        }

        self::fail('The level has no long wall with free space along it');
    }

    public function testPlayerStepsUpALowStepAndIsStoppedByAHighOne(): void
    {
        $level = $this->getLevel();
        $sectors = SectorState::createFromMap($level->map);
        $collider = new Collider($level, $sectors);
        $physics = new PlayerPhysics($collider, $this->getGameData()->tuning->physics);

        $climbed = false;
        $stopped = false;

        foreach ($level->map->linedefs as $linedef) {
            if ($linedef->leftSidedef === null || ($linedef->flags & LinedefFlag::BLOCKING) !== 0) {
                continue;
            }

            $map = $level->map;
            $from = $map->vertexes[$linedef->startVertex];
            $to = $map->vertexes[$linedef->endVertex];
            $length = \hypot($to->x - $from->x, $to->y - $from->y);

            if ($length < 96) {
                continue;
            }

            $right = $map->sidedefs[$linedef->rightSidedef]->sector;
            $left = $map->sidedefs[$linedef->leftSidedef]->sector;
            $rightFloor = $map->sectors[$right]->floorHeight;
            $leftFloor = $map->sectors[$left]->floorHeight;
            $height = \abs($rightFloor - $leftFloor);
            $ceiling = \min($map->sectors[$right]->ceilingHeight, $map->sectors[$left]->ceilingHeight);

            if ($height < 8 || $ceiling - \max($rightFloor, $leftFloor) < 72) {
                continue;
            }

            $normalX = ($to->y - $from->y) / $length;
            $normalY = -($to->x - $from->x) / $length;
            $sign = $rightFloor < $leftFloor ? 1.0 : -1.0;
            $lowFloor = \min($rightFloor, $leftFloor);

            $startX = ($from->x + $to->x) / 2 + $sign * $normalX * 30;
            $startY = ($from->y + $to->y) / 2 + $sign * $normalY * 30;
            $angle = \atan2(-$sign * $normalY, -$sign * $normalX);

            if (
                !$collider->check($startX, $startY, $lowFloor)->isFree
                || $collider->check($startX, $startY, $lowFloor)->floorZ !== (float) $lowFloor
            ) {
                continue;
            }

            $player = new Player($startX, $startY, $lowFloor, $angle);

            for ($tic = 0; $tic < 20; ++$tic) {
                $physics->tic($player, new PlayerInput(PlayerInput::WALK_FORWARD));
            }

            $crossed = ($player->x - $from->x) * -$sign * $normalX + ($player->y - $from->y) * -$sign * $normalY > 0;

            if ($height <= Collider::STEP_HEIGHT && $crossed && !$climbed) {
                self::assertSame((float) \max($rightFloor, $leftFloor), $player->z, 'stands on the higher floor');
                $climbed = true;
            }

            if ($height > Collider::STEP_HEIGHT && !$crossed && !$stopped) {
                self::assertSame((float) $lowFloor, $player->z, 'stays on the lower floor');
                $stopped = true;
            }

            if ($climbed && $stopped) {
                return;
            }
        }

        self::assertTrue($climbed, 'a step that the player can climb');
        self::assertTrue($stopped, 'a step that stops the player');
    }

    /**
     * @return list<array{int, int, int, int}>
     */
    private function findOneSidedLines(MapData $map): array
    {
        $lines = [];

        foreach ($map->linedefs as $linedef) {
            if ($linedef->leftSidedef !== null) {
                continue;
            }

            $from = $map->vertexes[$linedef->startVertex];
            $to = $map->vertexes[$linedef->endVertex];
            $lines[] = [$from->x, $from->y, $to->x, $to->y];
        }

        return $lines;
    }

    private function getDistance(float $x, float $y, int $x1, int $y1, int $x2, int $y2): float
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $along = \max(0.0, \min(1.0, (($x - $x1) * $dx + ($y - $y1) * $dy) / ($dx * $dx + $dy * $dy)));

        return \hypot($x - ($x1 + $along * $dx), $y - ($y1 + $along * $dy));
    }

    private function isRunwayFree(
        Collider $collider,
        float $x,
        float $y,
        float $directionX,
        float $directionY,
        int $length,
    ): bool
    {
        for ($distance = 0; $distance <= $length; $distance += 20) {
            $floorZ = $collider->check($x, $y, 0.0)->floorZ;
            $probe = $collider->check($x + $directionX * $distance, $y + $directionY * $distance, $floorZ);

            if (!$probe->isFree) {
                return false;
            }
        }

        return true;
    }
}
