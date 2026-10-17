<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

use Serafim\Doom\Game\Data\Tuning\CombatTuning;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Mobj;

/**
 * What a hit does to a thing: `P_DamageMobj` and `P_KillMobj`.
 */
final readonly class DamageSystem
{
    public function __construct(
        private GameContext $context,
        private CombatTuning $combat,
    ) {}

    /**
     * Hurts a thing that can be shot: it dies when its health is gone, or flinches by chance. A monster that the player
     * hurt turns on the player.
     *
     * @param bool $byPlayer the hit comes from the player or from what the player set off
     */
    public function damage(Mobj $target, int $amount, bool $byPlayer = true): void
    {
        if (!$target->isShootable || $target->health <= 0) {
            return;
        }

        $target->health -= $amount;

        if ($target->health <= 0) {
            $this->kill($target);

            return;
        }

        $painState = $target->definition?->painState;

        if ($painState !== null && $this->context->random->nextByte() < $target->definition->painChance) {
            $target->ai->isJustHit = true;
            $this->context->states->set($target, $painState);
        }

        $target->ai->reactionTime = 0;

        if (!$byPlayer || $target->ai->threshold > 0 || $target->definition?->seeState === null) {
            return;
        }

        $target->ai->hasTarget = true;
        $target->ai->threshold = $this->combat->baseThreshold;

        if ($target->state === $target->definition->spawnState) {
            $this->context->states->set($target, $target->definition->seeState);
        }
    }

    /**
     * Hurts the player: the armor takes its part.
     */
    public function damagePlayer(int $amount): void
    {
        $this->context->stats->damage($amount);
    }

    private function kill(Mobj $target): void
    {
        $definition = $target->definition;

        if ($definition === null) {
            return;
        }

        $target->isShootable = false;
        $target->isCorpse = true;
        $target->ai->hasTarget = false;
        $target->height /= 4;

        if ($definition->isCountedAsKill) {
            ++$this->context->stats->kills;
        }

        $state = $definition->deathState;

        if ($target->health < -$definition->health && $definition->xdeathState !== null) {
            $state = $definition->xdeathState;
        }

        $this->context->states->set($target, $state);

        if ($target->stateTics > 0) {
            $target->stateTics = \max(1, $target->stateTics - ($this->context->random->nextByte() & 3));
        }

        $drop = null;

        if ($definition->dropName !== null) {
            $drop = $this->context->spawner->catalog->getByName($definition->dropName);
        }

        if ($drop !== null) {
            $item = $this->context->spawner->create($drop, $target->x, $target->y, $target->z);
            $item->isDropped = true;

            $this->context->things->add($item);
        }
    }
}
