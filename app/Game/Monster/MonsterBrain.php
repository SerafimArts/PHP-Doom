<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

use Serafim\Doom\Game\Data\Tuning\CombatTuning;
use Serafim\Doom\Game\Data\Tuning\MonsterTuning;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\Thing\StateAction;
use Serafim\Doom\Game\Thing\StateActionHandlerInterface;

/**
 * What a monster thinks: it waits until it notices the player, walks toward the player and attacks (`A_Look`,
 * `A_Chase`, `A_FaceTarget`). The monsters here fight the player only.
 */
final readonly class MonsterBrain implements StateActionHandlerInterface
{
    /**
     * Distance the missile attack is judged from, and how much farther a monster that cannot claw shoots
     */
    private const float ONE_EIGHTH = \M_PI / 4;

    public function __construct(
        private GameContext $context,
        private NoiseAlert $noise,
        private MonsterMover $mover,
        private MonsterAttacks $attacks,
        private RadiusAttack $radius,
        private CombatTuning $combat,
        private MonsterTuning $monster,
    ) {}

    public function handle(Mobj $thing, StateAction $action): void
    {
        match ($action) {
            StateAction::LOOK => $this->look($thing),
            StateAction::CHASE => $this->chase($thing),
            StateAction::FACE_TARGET => $this->faceTarget($thing),
            StateAction::POS_ATTACK => $this->attack($thing, $this->attacks->shootBullet(...)),
            StateAction::SPOS_ATTACK => $this->attack($thing, $this->attacks->shootPellets(...)),
            StateAction::TROOP_ATTACK => $this->attack($thing, $this->attacks->attackAsTroop(...)),
            StateAction::EXPLODE => $this->radius->explode($thing),
            StateAction::NONE, StateAction::FALL => null,
        };
    }

    /**
     * A monster that has not noticed the player stands and looks: it wakes up when the noise of a shot reached its
     * sector, or when it sees the player (`A_Look`).
     */
    private function look(Mobj $monster): void
    {
        $monster->ai->threshold = 0;

        $sector = $this->context->level->findSectorAt($monster->x, $monster->y);

        if ($this->noise->isReached($sector) && !$this->context->stats->isDead()) {
            if (!$monster->ai->isAmbush || $this->attacks->canSeePlayer($monster)) {
                $monster->ai->hasTarget = true;
                $this->wake($monster);

                return;
            }
        }

        if ($this->lookForPlayer($monster, false)) {
            $this->wake($monster);
        }
    }

    private function wake(Mobj $monster): void
    {
        $seeState = $monster->definition?->seeState;

        if ($seeState !== null) {
            $this->context->states->set($monster, $seeState);
        }
    }

    /**
     * The monster targets the player when it sees the player. Without `$isAllAround` it does not notice the player
     * behind its back unless the player is close (`P_LookForPlayers`).
     */
    private function lookForPlayer(Mobj $monster, bool $isAllAround): bool
    {
        if ($this->context->stats->isDead() || !$this->attacks->canSeePlayer($monster)) {
            return false;
        }

        if (!$isAllAround) {
            $toPlayer = \atan2($this->context->player->y - $monster->y, $this->context->player->x - $monster->x);
            $turn = \fmod($toPlayer - $monster->angle + 3 * \M_PI, 2 * \M_PI) - \M_PI;
            $distance = Distance::approximate(
                $this->context->player->x - $monster->x,
                $this->context->player->y - $monster->y,
            );

            if (\abs($turn) > \M_PI / 2 && $distance > $this->combat->meleeRange) {
                return false;
            }
        }

        $monster->ai->hasTarget = true;

        return true;
    }

    private function faceTarget(Mobj $monster): void
    {
        if (!$monster->ai->hasTarget) {
            return;
        }

        $monster->ai->isAmbush = false;
        $monster->angle = \atan2($this->context->player->y - $monster->y, $this->context->player->x - $monster->x);
    }

    /**
     * The monster turns toward the player, then fires.
     *
     * @param \Closure(Mobj): void $attack
     */
    private function attack(Mobj $monster, \Closure $attack): void
    {
        if (!$monster->ai->hasTarget) {
            return;
        }

        $this->faceTarget($monster);
        $attack($monster);
    }

    /**
     * One tic of the hunt: the monster turns to the direction of its walk, then attacks when it can, otherwise takes a
     * step (`A_Chase`).
     */
    private function chase(Mobj $monster): void
    {
        $this->tickTimers($monster);
        $this->turnToDirection($monster);

        if (!$monster->ai->hasTarget || $this->context->stats->isDead()) {
            if (!$this->lookForPlayer($monster, true)) {
                $this->context->states->set($monster, $monster->definition?->spawnState);
            }

            return;
        }

        if ($monster->ai->isJustAttacked) {
            $monster->ai->isJustAttacked = false;
            $this->mover->chooseDirection($monster, $this->context->player->x, $this->context->player->y);

            return;
        }

        if ($this->tryAttack($monster)) {
            return;
        }

        if (--$monster->ai->moveCount < 0 || !$this->mover->move($monster)) {
            $this->mover->chooseDirection($monster, $this->context->player->x, $this->context->player->y);
        }
    }

    private function tickTimers(Mobj $monster): void
    {
        if ($monster->ai->reactionTime > 0) {
            --$monster->ai->reactionTime;
        }

        if ($monster->ai->threshold > 0) {
            if (!$monster->ai->hasTarget || $this->context->stats->isDead()) {
                $monster->ai->threshold = 0;
            } else {
                --$monster->ai->threshold;
            }
        }
    }

    /**
     * The monster claws when the player is close, and fires when the player is in range and the monster is not walking.
     *
     * @return bool the monster started an attack
     */
    private function tryAttack(Mobj $monster): bool
    {
        $definition = $monster->definition;

        if ($definition?->meleeState !== null && $this->attacks->isInMeleeRange($monster)) {
            $this->context->states->set($monster, $definition->meleeState);

            return true;
        }

        if ($definition?->missileState !== null && $monster->ai->moveCount === 0 && $this->isInMissileRange($monster)) {
            $this->context->states->set($monster, $definition->missileState);
            $monster->ai->isJustAttacked = true;

            return true;
        }

        return false;
    }

    /**
     * The monster fires from a distance with a chance that grows as the player comes closer (`P_CheckMissileRange`).
     */
    private function isInMissileRange(Mobj $monster): bool
    {
        if (!$this->attacks->canSeePlayer($monster)) {
            return false;
        }

        if ($monster->ai->isJustHit) {
            $monster->ai->isJustHit = false;

            return true;
        }

        if ($monster->ai->reactionTime > 0) {
            return false;
        }

        $distance = Distance::approximate(
            $this->context->player->x - $monster->x,
            $this->context->player->y - $monster->y,
        ) - $this->monster->missileNear;

        if ($monster->definition?->meleeState === null) {
            $distance -= $this->monster->noClawBonus;
        }

        $limit = \min($this->monster->maxMissileDistance, (int) \floor($distance));

        return $this->context->random->nextByte() >= $limit;
    }

    /**
     * Turns the monster by an eighth of a circle toward the direction it walks in.
     */
    private function turnToDirection(Mobj $monster): void
    {
        if ($monster->ai->moveDirection === Direction::NONE) {
            return;
        }

        $turns = (int) \floor(\fmod($monster->angle + 4 * \M_PI, 2 * \M_PI) / self::ONE_EIGHTH) & 7;
        $difference = ($turns - $monster->ai->moveDirection->value) & 7;

        if ($difference === 0) {
            $monster->angle = $turns * self::ONE_EIGHTH;

            return;
        }

        if ($difference >= 4) {
            $monster->angle = ($turns + 1) * self::ONE_EIGHTH;

            return;
        }

        $monster->angle = ($turns - 1) * self::ONE_EIGHTH;
    }
}
