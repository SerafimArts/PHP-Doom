<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Physics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\PlayerPhysics;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(PlayerPhysics::class)]
#[CoversClass(Collider::class)]
final class PlayerPhysicsStressTest extends TestCase
{
    private const int EPISODES = 20;

    private const int TICS = 1500;

    /**
     * Distance of the probes that tell whether the player is wedged
     */
    private const float PROBE_DISTANCE = 0.25;

    public function testRandomWalksNeverLeaveThePlayerInsideOfTheLevelGeometry(): void
    {
        $level = $this->getLevel();
        $bounds = $level->bounds;
        $embedded = [];
        $wedged = [];

        for ($episode = 0; $episode < self::EPISODES; ++$episode) {
            $random = new Randomizer(new Mt19937(1000 + $episode));
            $world = $this->createWorld($level, [], $episode + 1);
            $collider = new Collider($level, $world->state->sectors, $world->things);
            $player = $world->player;

            for ($try = 0; $try < 500; ++$try) {
                $x = $random->getInt($bounds->minX, $bounds->maxX);
                $y = $random->getInt($bounds->minY, $bounds->maxY);
                $floor = (float) $world->state->sectors->floorHeights[$level->findSector($x, $y)];
                $check = $collider->check((float) $x, (float) $y, $floor);

                if ($check->isFree && $check->ceilingZ - $check->floorZ >= $player::HEIGHT) {
                    $player->x = (float) $x;
                    $player->y = (float) $y;
                    $player->z = $floor;
                    $player->floorZ = $floor;

                    break;
                }
            }

            $player->yaw = $random->getInt(0, 628) / 100.0;
            $turn = 0.0;

            for ($tic = 0; $tic < self::TICS; ++$tic) {
                if ($tic % 45 === 0) {
                    $turn = $random->getInt(-100, 100) / 400.0;
                }

                if ($tic % 45 === 12) {
                    $turn = 0.0;
                }

                $world->stats->health = 100;
                $world->tick(new PlayerInput(
                    forwardMove: PlayerInput::RUN_FORWARD,
                    sideMove: $this->pick($random, 10, PlayerInput::RUN_SIDE),
                    turn: $turn,
                    isUsing: $this->pick($random, 8, 1) === 1,
                    isFiring: $this->pick($random, 4, 1) === 1,
                ));

                if (!$collider->check($player->x, $player->y, $player->z)->isFree) {
                    $embedded[] = \sprintf('episode %d tic %d at %.2f %.2f', $episode, $tic, $player->x, $player->y);

                    break;
                }

                if ($this->isWedged($collider, $player->x, $player->y, $player->z)) {
                    $wedged[] = \sprintf('episode %d tic %d at %.4f %.4f', $episode, $tic, $player->x, $player->y);

                    break;
                }
            }
        }

        self::assertSame([], $embedded, 'the player is inside of a wall or of a thing');
        self::assertSame([], $wedged, 'every direction a quarter of a unit away is blocked');
    }

    /**
     * One time in `$odds` gives the value, otherwise zero.
     */
    private function pick(Randomizer $random, int $odds, int $value): int
    {
        if ($random->getInt(0, $odds - 1) === 0) {
            return $value;
        }

        return 0;
    }

    private function isWedged(Collider $collider, float $x, float $y, float $z): bool
    {
        $diagonal = self::PROBE_DISTANCE * \M_SQRT1_2;

        foreach ([
            [self::PROBE_DISTANCE, 0.0],
            [-self::PROBE_DISTANCE, 0.0],
            [0.0, self::PROBE_DISTANCE],
            [0.0, -self::PROBE_DISTANCE],
            [$diagonal, $diagonal],
            [-$diagonal, $diagonal],
            [$diagonal, -$diagonal],
            [-$diagonal, -$diagonal],
        ] as [$offsetX, $offsetY]) {
            if ($collider->check($x + $offsetX, $y + $offsetY, $z)->isFree) {
                return false;
            }
        }

        return true;
    }
}
