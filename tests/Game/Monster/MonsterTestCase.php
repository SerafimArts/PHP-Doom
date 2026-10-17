<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Monster\Sight;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\LineTable;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\World;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
/**
 * Puts monsters into E1M1 and plays their tics.
 */
abstract class MonsterTestCase extends TestCase
{
    protected const int ZOMBIEMAN = 3004;

    protected const int IMP = 3001;

    protected const int BARREL = 2035;


    /**
     * Puts a monster of the type at a distance from the player, in plain sight, and makes it face the player.
     *
     * @param bool $isAsleep the monster looks away from the player
     */
    protected function addMonster(World $world, int $type, float $distance, bool $isAsleep = false): Mobj
    {
        [$position, $floor] = $this->findClearSpot($world, $distance);

        $spawner = $this->createSpawner();
        $monster = $spawner->create(
            $this->getGameData()->things->findByType($type) ?? throw new \LogicException('unknown thing'),
            $position[0],
            $position[1],
            $floor,
            \atan2($world->player->y - $position[1], $world->player->x - $position[0]),
        );

        $sector = $this->getLevel()->findSector((int) $position[0], (int) $position[1]);
        $monster->ceilingZ = (float) $world->state->sectors->ceilingHeights[$sector];

        $world->things->add($monster);

        if ($isAsleep) {
            $monster->angle += \M_PI;
        }

        return $monster;
    }

    /**
     * @return array{array{float, float}, float}
     */
    protected function findClearSpot(World $world, float $distance): array
    {
        $level = $this->getLevel();
        $collider = new Collider($level, $world->state->sectors);
        $monster = $this->getGameData()->tuning->monster;
        $sight = new Sight($level, $world->state->sectors, new LineTable($level), $monster);
        $player = $world->player;

        foreach ([1.0, 0.8, 0.6] as $scale) {
            for ($step = 0; $step < 32; ++$step) {
                $angle = $step * \M_PI / 16;
                $x = $player->x + \cos($angle) * $distance * $scale;
                $y = $player->y + \sin($angle) * $distance * $scale;
                $floor = (float) $world->state->sectors->floorHeights[$level->findSector((int) $x, (int) $y)];

                if ($floor !== $player->z) {
                    continue;
                }

                if (!$collider->checkBody($x, $y, $floor, 20.0, 56.0, null, $player, true)->isFree) {
                    continue;
                }

                if ($sight->canSee($x, $y, $floor, 56.0, $player->x, $player->y, $player->z, Player::HEIGHT)) {
                    return [[$x, $y], $floor];
                }
            }
        }

        self::fail('there is no clear spot near the start of the player');
    }

    protected function createLines(World $world): \Serafim\Doom\Game\Special\LineSpecials
    {
        $level = $this->getLevel();
        $collider = new Collider($level, $world->state->sectors);
        $thinkers = new \Serafim\Doom\Game\Special\ThinkerList();
        $context = new \Serafim\Doom\Game\Special\SpecialContext(
            $level,
            $world->state->sectors,
            new \Serafim\Doom\Game\Special\PlaneMover($world->state->sectors, $collider, $world->player),
            $thinkers,
            new \Serafim\Doom\Game\Random(),
            $this->getGameData()->specials,
            $this->getGameData()->animations,
        );

        return new \Serafim\Doom\Game\Special\LineSpecials($context, $world->state->sides, []);
    }
}
