<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

use Serafim\Doom\Game\Combat\DamageSystem;
use Serafim\Doom\Game\Combat\Hitscan;
use Serafim\Doom\Game\Combat\Impacts;
use Serafim\Doom\Game\Data\Tuning\CombatTuning;
use Serafim\Doom\Game\Data\Tuning\MonsterTuning;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Player;

/**
 * What the monsters do to the player: bullets of the zombieman and the shotgun guy, the claw and the fireball of the
 * imp (`A_PosAttack`, `A_SPosAttack`, `A_TroopAttack`).
 */
final readonly class MonsterAttacks
{
    public function __construct(
        private GameContext $context,
        private Hitscan $hitscan,
        private Impacts $impacts,
        private DamageSystem $damage,
        private MissileSystem $missiles,
        private Sight $sight,
        private CombatTuning $combat,
        private MonsterTuning $monster,
    ) {}

    /**
     * The player is close enough for a claw (`P_CheckMeleeRange`).
     */
    public function isInMeleeRange(Mobj $monster): bool
    {
        $distance = Distance::approximate(
            $this->context->player->x - $monster->x,
            $this->context->player->y - $monster->y,
        );

        if ($distance >= $this->combat->meleeRange - 20.0 + Player::RADIUS) {
            return false;
        }

        return $this->canSeePlayer($monster);
    }

    public function canSeePlayer(Mobj $monster): bool
    {
        return $this->sight->canSee(
            $monster->x,
            $monster->y,
            $monster->z,
            $monster->height,
            $this->context->player->x,
            $this->context->player->y,
            $this->context->player->z,
            Player::HEIGHT,
        );
    }

    /**
     * One bullet, 3 to 15 points of damage.
     */
    public function shootBullet(Mobj $monster): void
    {
        $slope = $this->getSlope($monster);
        $this->fire($monster, $monster->angle + $this->getSpread(), $slope);
    }

    /**
     * Three pellets that spread apart.
     */
    public function shootPellets(Mobj $monster): void
    {
        $slope = $this->getSlope($monster);

        for ($pellet = 0; $pellet < $this->monster->pellets; ++$pellet) {
            $this->fire($monster, $monster->angle + $this->getSpread(), $slope);
        }
    }

    /**
     * The imp tears the player with its claws when it is close, and throws a fireball when it is not.
     */
    public function attackAsTroop(Mobj $monster): void
    {
        if ($this->isInMeleeRange($monster)) {
            $this->damage->damagePlayer((($this->context->random->nextByte() % 8) + 1) * 3);

            return;
        }

        $this->missiles->launch($monster);
    }

    private function fire(Mobj $monster, float $angle, float $slope): void
    {
        $x = $monster->x;
        $y = $monster->y;
        $z = $this->getMuzzleHeight($monster);

        $wall = $this->hitscan->shoot($x, $y, $z, $angle, $this->combat->missileRange, $slope, $monster, true);
        $wallDistance = $this->combat->missileRange + 1.0;

        if ($wall !== null) {
            $wallDistance = \hypot($wall->x - $x, $wall->y - $y);
        }

        $entry = $this->hitscan->getBoxEntry(
            $x,
            $y,
            \cos($angle) * $this->combat->missileRange,
            \sin($angle) * $this->combat->missileRange,
            $this->context->player->x,
            $this->context->player->y,
            Player::RADIUS,
        );

        if ($entry !== null) {
            $distance = $entry * $this->combat->missileRange;
            $height = $z + $slope * $distance;

            if (
                $distance < $wallDistance
                && $height >= $this->context->player->z
                && $height <= $this->context->player->z + Player::HEIGHT
            ) {
                $this->damage->damagePlayer((($this->context->random->nextByte() % 5) + 1) * 3);

                return;
            }
        }

        if ($wall !== null) {
            $this->impacts->puff($wall, false);
        }
    }

    /**
     * The slope of a shot at the middle of the player, a monster does not aim at what is too high or too low.
     */
    private function getSlope(Mobj $monster): float
    {
        $distance = \hypot($this->context->player->x - $monster->x, $this->context->player->y - $monster->y);

        if ($distance < 1.0) {
            return 0.0;
        }

        $slope = ($this->context->player->z + Player::HEIGHT / 2 - $this->getMuzzleHeight($monster)) / $distance;

        if (\abs($slope) > $this->combat->aimSlope) {
            return 0.0;
        }

        return $slope;
    }

    private function getMuzzleHeight(Mobj $monster): float
    {
        return $monster->z + $monster->height / 2 + 8.0;
    }

    private function getSpread(): float
    {
        $first = $this->context->random->nextByte();
        $second = $this->context->random->nextByte();

        return ($first - $second) * $this->combat->getMonsterSpreadStep();
    }
}
