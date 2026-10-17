<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

use Serafim\Doom\Game\Combat\DamageSystem;
use Serafim\Doom\Game\Data\Tuning\CombatTuning;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Player;

/**
 * The blast of an explosion: it hurts everything that is around it and that it can reach, less the farther the thing is
 * (`P_RadiusAttack`, `A_Explode`).
 */
final readonly class RadiusAttack
{
    public function __construct(
        private GameContext $context,
        private DamageSystem $damage,
        private Sight $sight,
        private CombatTuning $combat,
    ) {}

    /**
     * Hurts what is within the damage in map units of the center of the explosion and in sight of it.
     */
    public function explode(Mobj $center, ?int $damage = null): void
    {
        $damage ??= $this->combat->barrelDamage;

        foreach ($this->context->things->all() as $thing) {
            if (!$thing->isShootable || $thing->isRemoved || $thing === $center) {
                continue;
            }

            $amount = $damage - $this->getDistance($center, $thing->x, $thing->y, $thing->radius);

            if ($amount <= 0.0 || !$this->isReached($center, $thing->x, $thing->y, $thing->z, $thing->height)) {
                continue;
            }

            $this->damage->damage($thing, (int) $amount);
        }

        if ($this->context->stats->isDead()) {
            return;
        }

        $player = $this->context->player;
        $amount = $damage - $this->getDistance($center, $player->x, $player->y, Player::RADIUS);

        if ($amount > 0.0 && $this->isReached($center, $player->x, $player->y, $player->z, Player::HEIGHT)) {
            $this->damage->damagePlayer((int) $amount);
        }
    }

    /**
     * How far a thing is from the center, from the edge of the thing: the larger of the distances along the axes.
     */
    private function getDistance(Mobj $center, float $x, float $y, float $radius): float
    {
        return \max(0.0, \max(\abs($x - $center->x), \abs($y - $center->y)) - $radius);
    }

    private function isReached(Mobj $center, float $x, float $y, float $z, float $height): bool
    {
        return $this->sight->canSee($x, $y, $z, $height, $center->x, $center->y, $center->z, $center->height);
    }
}
