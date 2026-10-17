<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\Special\DoorThinker;
use Serafim\Doom\Game\Special\LineSpecials;
use Serafim\Doom\Game\Special\PlatformThinker;
use Serafim\Doom\Game\Special\SectorSpecials;
use Serafim\Doom\Game\World;
use Serafim\Doom\Level\Geometry\WallPartType;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(World::class)]
#[CoversClass(LineSpecials::class)]
#[CoversClass(SectorSpecials::class)]
#[CoversClass(DoorThinker::class)]
#[CoversClass(PlatformThinker::class)]
final class SpecialsTest extends TestCase
{
    private const int DOOR_LINE = 151;

    private const int DOOR_SECTOR = 4;

    private const int LIFT_LINE = 195;

    private const int LIFT_SECTOR = 70;

    private const int TRAP_SECTOR = 59;

    private const int EXIT_LINE = 330;



    public function testDoorOpensWaitsAndClosesAgain(): void
    {
        $world = $this->createWorldAt(1500, -2496, 0.0);
        $ceilings = $world->state->sectors->ceilingHeights;

        self::assertSame(0, $ceilings[self::DOOR_SECTOR]);

        $world->tick(new PlayerInput(isUsing: true));

        $heights = [];

        for ($tic = 1; $tic <= 260; ++$tic) {
            $world->tick(new PlayerInput());
            $heights[$tic] = $world->state->sectors->ceilingHeights[self::DOOR_SECTOR];
        }

        self::assertSame(
            4.0,
            (float) $heights[1],
            'the door rises 2 units per tic, the first one went in the tic of the use',
        );
        self::assertSame(68.0, (float) \max($heights), '4 units under the lowest ceiling around');
        self::assertSame(68.0, (float) $heights[100], 'it waits fully open');
        self::assertLessThan(68.0, $heights[200], 'it starts to close after 150 tics');
        self::assertSame(0.0, (float) $heights[260], 'it is closed again');
    }

    public function testUsingAnOpeningDoorAgainClosesItAtOnce(): void
    {
        $world = $this->createWorldAt(1500, -2496, 0.0);

        $world->tick(new PlayerInput(isUsing: true));

        for ($tic = 0; $tic < 20; ++$tic) {
            $world->tick(new PlayerInput());
        }

        $opening = $world->state->sectors->ceilingHeights[self::DOOR_SECTOR];

        $world->tick(new PlayerInput(isUsing: true));
        $world->tick(new PlayerInput());
        $world->tick(new PlayerInput());

        self::assertLessThan($opening, $world->state->sectors->ceilingHeights[self::DOOR_SECTOR]);
    }

    public function testDoorDoesNotCrushThePlayerAndOpensAgain(): void
    {
        $world = $this->createWorldAt(1500, -2496, 0.0);

        $world->tick(new PlayerInput(isUsing: true));

        for ($tic = 0; $tic < 60; ++$tic) {
            $world->tick(new PlayerInput());
        }

        $player = $world->player;
        $player->x = 1560;
        $player->y = -2496;

        for ($tic = 0; $tic < 400; ++$tic) {
            $world->tick(new PlayerInput());
            $sectors = $world->state->sectors;

            self::assertGreaterThanOrEqual(
                Player::HEIGHT,
                $sectors->ceilingHeights[self::DOOR_SECTOR] - $sectors->floorHeights[self::DOOR_SECTOR],
                'tic ' . $tic,
            );
        }
    }

    public function testWalkingOverTheLiftLineLowersTheLiftAndRaisesItAfterAWhile(): void
    {
        $level = $this->getLevel();
        $line = $level->map->linedefs[self::LIFT_LINE];
        $from = $level->map->vertexes[$line->startVertex];
        $to = $level->map->vertexes[$line->endVertex];

        $length = \hypot($to->x - $from->x, $to->y - $from->y);
        $normalX = ($to->y - $from->y) / $length;
        $normalY = -($to->x - $from->x) / $length;

        $world = $this->createWorldAt(
            ($from->x + $to->x) / 2 + $normalX * 40,
            ($from->y + $to->y) / 2 + $normalY * 40,
            \atan2(-$normalY, -$normalX),
        );
        $floors = $world->state->sectors;
        $start = $floors->floorHeights[self::LIFT_SECTOR];
        $lowest = $start;

        for ($tic = 0; $tic < 12; ++$tic) {
            $world->tick(new PlayerInput(PlayerInput::WALK_FORWARD));
        }

        for ($tic = 0; $tic < 300; ++$tic) {
            $world->tick(new PlayerInput());
            $lowest = \min($lowest, $floors->floorHeights[self::LIFT_SECTOR]);
        }

        self::assertSame(-48.0, (float) $lowest, 'the lift goes down to the lowest floor around');
        self::assertSame((float) $start, (float) $floors->floorHeights[self::LIFT_SECTOR], 'and comes back');
    }

    public function testWalkingOverTheTrapLineLowersTheFloorOnce(): void
    {
        $world = $this->createWorldAt(3008, -4190, \M_PI / 2);
        $floors = $world->state->sectors;

        self::assertSame(96, $floors->floorHeights[self::TRAP_SECTOR]);

        for ($tic = 0; $tic < 12; ++$tic) {
            $world->tick(new PlayerInput(PlayerInput::WALK_FORWARD));
        }

        for ($tic = 0; $tic < 80; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertSame(-40.0, (float) $floors->floorHeights[self::TRAP_SECTOR]);
    }

    public function testExitSwitchEndsTheLevelAndTurnsOn(): void
    {
        $world = $this->createWorldAt(2950, -4768, \M_PI);
        $sidedef = $this->getLevel()->map->linedefs[self::EXIT_LINE]->rightSidedef;

        self::assertSame('SW1STRTN', $world->state->sides->findTexture($sidedef, WallPartType::MIDDLE));
        self::assertFalse($world->isExitRequested());

        $world->tick(new PlayerInput(isUsing: true));

        self::assertTrue($world->isExitRequested());
        self::assertSame('SW2STRTN', $world->state->sides->findTexture($sidedef, WallPartType::MIDDLE));
    }

    public function testUsingAWallInTheMiddleOfNowhereDoesNothing(): void
    {
        $world = $this->createWorldAt(1056, -3616, \M_PI / 2);
        $version = $world->state->sides->getVersion();

        $world->tick(new PlayerInput(isUsing: true));

        self::assertFalse($world->isExitRequested());
        self::assertSame($version, $world->state->sides->getVersion());
    }

    public function testNukageHurtsEveryThirtyTwoTics(): void
    {
        $level = $this->getLevel();
        $sector = 13;

        foreach ($level->geometry->polygons as $polygon) {
            if ($polygon->sector === $sector) {
                $centroid = $polygon->getCentroid();

                break;
            }
        }

        $world = $this->createWorldAt($centroid->x ?? 0.0, $centroid->y ?? 0.0, 0.0);

        for ($tic = 0; $tic < 200; ++$tic) {
            $world->tick(new PlayerInput());
        }

        self::assertSame(100 - 7 * 5, $world->stats->health);
    }

    public function testSecretSectorsAreCountedOnce(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorldAt(0, 0, 0.0);

        self::assertSame(3, $world->getTotalSecrets());

        $found = 0;

        foreach ($level->map->sectors as $index => $sector) {
            if ($sector->special !== 9 || $sector->ceilingHeight - $sector->floorHeight < Player::HEIGHT) {
                continue;
            }

            foreach ($level->geometry->polygons as $polygon) {
                if ($polygon->sector !== $index) {
                    continue;
                }

                $centroid = $polygon->getCentroid();
                $world->player->x = $centroid->x;
                $world->player->y = $centroid->y;
                $world->player->z = $world->state->sectors->floorHeights[$index];
                $world->tick(new PlayerInput());
                $world->tick(new PlayerInput());
                ++$found;

                break;
            }
        }

        self::assertSame(
            $found,
            $world->stats->secrets,
            'a secret sector that is closed (a hidden door) cannot be entered',
        );
        self::assertSame(2, $found);
    }

    public function testNukageFloorShowsAllFramesOfItsAnimation(): void
    {
        $world = $this->createWorldAt(1056, -3616, 0.0);
        $sector = \array_search('NUKAGE3', $world->state->sectors->floorFlats, true);

        self::assertIsInt($sector);

        $frames = [];

        for ($tic = 0; $tic < 60; ++$tic) {
            $world->tick(new PlayerInput());
            $frames[$world->state->sectors->floorFlats[$sector]] = true;
        }

        $names = \array_keys($frames);
        \sort($names);

        self::assertSame(['NUKAGE1', 'NUKAGE2', 'NUKAGE3'], $names);
    }

    public function testLightEffectsStayBetweenTheDarkestAndTheOwnLight(): void
    {
        $world = $this->createWorldAt(1056, -3616, 0.0);
        $lights = $world->state->sectors->lights;
        $start = $lights;
        $changed = [];

        for ($tic = 0; $tic < 600; ++$tic) {
            $world->tick(new PlayerInput());

            foreach ($world->state->sectors->lights as $sector => $light) {
                self::assertGreaterThanOrEqual(0, $light);
                self::assertLessThanOrEqual(255, $light);
                self::assertLessThanOrEqual($start[$sector], $light);

                if ($light !== $start[$sector]) {
                    $changed[$sector] = true;
                }
            }
        }

        self::assertGreaterThanOrEqual(
            3,
            \count($changed),
            'the flickering, glowing and blinking sectors change their light',
        );
    }

    private function createWorldAt(float $x, float $y, float $yaw): World
    {
        $world = $this->createWorld($this->getLevel(), $this->getTextureNames());
        $world->things->replace([]);
        $world->player->x = $x;
        $world->player->y = $y;
        $world->player->yaw = $yaw;
        $world->player->z = $world->state->sectors->floorHeights[$this->getLevel()->findSector((int) $x, (int) $y)];

        return $world;
    }
}
