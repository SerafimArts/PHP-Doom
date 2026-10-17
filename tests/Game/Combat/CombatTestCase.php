<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Combat;

use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Combat\Hitscan;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\Weapon;
use Serafim\Doom\Game\World;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
/**
 * Puts a zombieman in front of the player in E1M1 and fires the weapons at it.
 */
abstract class CombatTestCase extends TestCase
{
    protected const int RAISED = 20;


    /**
     * Puts the player in front of the first zombie of the level with the weapon raised and looking at it.
     *
     * @return array{World, Mobj}
     */
    protected function createDuel(Weapon $weapon = Weapon::PISTOL, float $distance = 100.0): array
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $zombie = null;

        foreach ($world->things as $thing) {
            if ($thing->type === 3004) {
                $zombie = $thing;

                break;
            }
        }

        \assert($zombie !== null);

        $collider = new Collider($level, $world->state->sectors);

        for ($step = 0; $step < 16; ++$step) {
            $angle = $step * \M_PI / 8;
            $x = $zombie->x + \cos($angle) * $distance;
            $y = $zombie->y + \sin($angle) * $distance;
            $floor = $world->state->sectors->floorHeights[$level->findSector((int) $x, (int) $y)];

            if (!$collider->check($x, $y, $floor)->isFree || !$this->isClear($world, $level, $x, $y, $zombie)) {
                continue;
            }

            $world->player->x = $x;
            $world->player->y = $y;
            $world->player->z = $floor;
            $world->player->yaw = \atan2($zombie->y - $y, $zombie->x - $x);

            break;
        }

        $world->stats->inventory->owned[Weapon::SHOTGUN->value] = true;
        $world->stats->inventory->ammo['shells'] = 20;

        if ($weapon !== Weapon::PISTOL) {
            $world->stats->weapon = $weapon;
            $world->weapons->bringUp();
        }

        for ($tic = 0; $tic < self::RAISED; ++$tic) {
            $world->tick(new PlayerInput());
        }

        return [$world, $zombie];
    }

    protected function isClear(World $world, Level $level, float $x, float $y, Mobj $zombie): bool
    {
        $combat = $this->getGameData()->tuning->combat;
        $context = $this->createContext($level, $world->state->sectors);
        $context->things->add($zombie);
        $hitscan = new Hitscan($context, $combat);
        $floor = (float) $world->state->sectors->floorHeights[$level->findSector((int) $x, (int) $y)];
        $target = $hitscan->aim($x, $y, $floor + 36.0, \atan2($zombie->y - $y, $zombie->x - $x), 1024.0);

        return $target?->target === $zombie;
    }

    protected function fire(World $world, int $tics): void
    {
        for ($tic = 0; $tic < $tics; ++$tic) {
            $world->tick(new PlayerInput(isFiring: true));
        }
    }
}
