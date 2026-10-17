<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Mobj;

use Serafim\Doom\Game\Monster\Direction;

/**
 * What a monster decides while it hunts: where it walks, whom it has noticed and how long it waits.
 */
final class MonsterState
{
    /**
     * Direction of the walk (`movedir`)
     */
    public Direction $moveDirection = Direction::NONE;

    /**
     * Tics left to walk in the direction before the next choice (`movecount`)
     */
    public int $moveCount = 0;

    /**
     * Tics before the monster attacks after it noticed the player (`reactiontime`)
     */
    public int $reactionTime = 0;

    /**
     * Tics the monster keeps its target against another one (`threshold`)
     */
    public int $threshold = 0;

    /**
     * The monster has noticed the player and hunts the player (`target`)
     */
    public bool $hasTarget = false;

    /**
     * The player has just hurt the monster, it fights back at once (`MF_JUSTHIT`)
     */
    public bool $isJustHit = false;

    /**
     * The monster has just attacked and chooses a new direction before it attacks again (`MF_JUSTATTACKED`)
     */
    public bool $isJustAttacked = false;

    /**
     * Wakes up only when it sees the player, not by a noise (`MF_AMBUSH`)
     */
    public bool $isAmbush = false;
}
