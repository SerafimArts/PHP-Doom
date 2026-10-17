<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

use Serafim\Doom\Game\Combat\DamageSystem;
use Serafim\Doom\Game\Data\Tuning\MonsterTuning;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\PositionCheck;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Level\SectorState;

/**
 * The fireballs that the monsters throw: they fly in a straight line and explode on a wall, the floor, the ceiling, or
 * on what they hit (`P_SpawnMissile`, `P_XYMovement` and `P_ExplodeMissile` for a missile).
 */
final readonly class MissileSystem
{
    /**
     * A missile starts this high over the feet of the monster
     */
    public function __construct(
        private GameContext $context,
        private Collider $collider,
        private DamageSystem $damage,
        private MonsterTuning $tuning,
    ) {}

    /**
     * Throws a fireball from a monster at the player.
     */
    public function launch(Mobj $source): void
    {
        $definition = $this->context->spawner->catalog->getByName('TROOP_SHOT');

        $angle = \atan2($this->context->player->y - $source->y, $this->context->player->x - $source->x);

        $missile = $this->context->spawner->create(
            $definition,
            $source->x,
            $source->y,
            $source->z + $this->tuning->launchHeight,
            $angle,
        );
        $state = $missile->missile;
        \assert($state !== null);
        $state->owner = $source;
        $missile->momentumX = $definition->speed * \cos($angle);
        $missile->momentumY = $definition->speed * \sin($angle);

        $steps = \max(
            1.0,
            Distance::approximate(
                $this->context->player->x - $source->x,
                $this->context->player->y - $source->y,
            ) / $definition->speed,
        );
        $missile->momentumZ = ($this->context->player->z - $source->z) / $steps;

        $missile->stateTics = \max(1, $missile->stateTics - ($this->context->random->nextByte() & 3));

        $sector = $this->context->level->findSectorAt($source->x, $source->y);
        $missile->floorZ = (float) $this->context->sectors->floorHeights[$sector];
        $missile->ceilingZ = (float) $this->context->sectors->ceilingHeights[$sector];

        $this->context->things->add($missile);

        $missile->x += $missile->momentumX / 2;
        $missile->y += $missile->momentumY / 2;
        $missile->z += $missile->momentumZ / 2;

        if (!$this->fits($missile)) {
            $this->explode($missile);
        }
    }

    /**
     * Moves the fireballs that fly.
     *
     * @param list<Mobj> $things
     */
    public function tick(array $things): void
    {
        foreach ($things as $thing) {
            if ($thing->missile?->isFlying === true && !$thing->isRemoved) {
                $this->fly($thing);
            }
        }
    }

    private function fly(Mobj $missile): void
    {
        $x = $missile->x + $missile->momentumX;
        $y = $missile->y + $missile->momentumY;
        $z = $missile->z + $missile->momentumZ;

        if ($this->hitThing($missile, $x, $y, $z)) {
            return;
        }

        $check = $this->collider->checkBody(
            $x,
            $y,
            $missile->z,
            $missile->radius,
            $missile->height,
            $missile,
            null,
            false,
            false,
        );

        if (!$check->isFree) {
            if ($check->blockingLine !== null && $this->isSkyWall($check->blockingLine)) {
                $missile->isRemoved = true;

                return;
            }

            $this->explode($missile);

            return;
        }

        $this->moveTo($missile, $x, $y, $z, $check);
    }

    /**
     * Moves the missile and explodes it against the floor or the ceiling.
     */
    private function moveTo(Mobj $missile, float $x, float $y, float $z, PositionCheck $check): void
    {
        $missile->x = $x;
        $missile->y = $y;
        $missile->z = $z;
        $missile->floorZ = $check->floorZ;
        $missile->ceilingZ = $check->ceilingZ;

        if ($z <= $check->floorZ) {
            $missile->z = $check->floorZ;
            $this->explode($missile);

            return;
        }

        if ($z + $missile->height > $check->ceilingZ) {
            $sector = $this->context->level->findSectorAt($x, $y);

            if ($this->context->sectors->ceilingFlats[$sector] === SectorState::SKY_FLAT) {
                $missile->isRemoved = true;

                return;
            }

            $this->explode($missile);
        }
    }

    /**
     * A missile that stands in a wall right after it was thrown explodes at once.
     */
    private function fits(Mobj $missile): bool
    {
        return $this->collider->checkBody(
            $missile->x,
            $missile->y,
            $missile->z,
            $missile->radius,
            $missile->height,
            $missile,
            null,
            false,
            false,
        )->isFree;
    }

    /**
     * The fireball meets the player or a monster on its way: it hurts what it hits and explodes.
     */
    private function hitThing(Mobj $missile, float $x, float $y, float $z): bool
    {
        $player = $this->context->player;
        $reach = $missile->radius + Player::RADIUS;

        if (!$this->context->stats->isDead()
            && \abs($x - $player->x) < $reach
            && \abs($y - $player->y) < $reach
            && $z <= $player->z + Player::HEIGHT
            && $z + $missile->height >= $player->z
        ) {
            $this->damage->damagePlayer((($this->context->random->nextByte() % 8) + 1) * $this->getDamage($missile));
            $this->explode($missile);

            return true;
        }

        foreach ($this->context->things->all() as $thing) {
            if ($thing === $missile->missile?->owner || !$thing->isShootable || $thing->isRemoved) {
                continue;
            }

            $reach = $missile->radius + $thing->radius;

            if (\abs($x - $thing->x) >= $reach
                || \abs($y - $thing->y) >= $reach
                || $z > $thing->z + $thing->height
                || $z + $missile->height < $thing->z
            ) {
                continue;
            }

            if ($thing->type !== $missile->missile?->owner?->type) {
                $roll = ($this->context->random->nextByte() % 8) + 1;
                $this->damage->damage($thing, $roll * $this->getDamage($missile), false);
            }

            $this->explode($missile);

            return true;
        }

        return false;
    }

    private function isSkyWall(int $line): bool
    {
        $linedef = $this->context->level->map->linedefs[$line];

        if ($linedef->leftSidedef === null) {
            return false;
        }

        $front = $this->context->level->map->sidedefs[$linedef->rightSidedef]->sector;
        $back = $this->context->level->map->sidedefs[$linedef->leftSidedef]->sector;

        return $this->context->sectors->ceilingFlats[$front] === SectorState::SKY_FLAT
            || $this->context->sectors->ceilingFlats[$back] === SectorState::SKY_FLAT;
    }

    /**
     * The fireball stops and plays its explosion (`P_ExplodeMissile`).
     */
    private function explode(Mobj $missile): void
    {
        $missile->momentumX = 0.0;
        $missile->momentumY = 0.0;
        $missile->momentumZ = 0.0;
        if ($missile->missile !== null) {
            $missile->missile->isFlying = false;
        }

        $this->context->states->set($missile, $missile->definition?->deathState);

        if (!$missile->isRemoved) {
            $missile->stateTics = \max(1, $missile->stateTics - ($this->context->random->nextByte() & 3));
        }
    }

    private function getDamage(Mobj $missile): int
    {
        return $missile->missile->damage ?? 0;
    }
}
