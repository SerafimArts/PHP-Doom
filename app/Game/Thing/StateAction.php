<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

/**
 * What a state of a thing does when the thing enters it (the `action` function of `state_t`).
 */
enum StateAction
{
    case NONE;

    /**
     * `A_Look`: the thing waits for a player to come into sight
     */
    case LOOK;

    /**
     * `A_Chase`: the monster walks toward the player and decides to attack
     */
    case CHASE;

    /**
     * `A_FaceTarget`
     */
    case FACE_TARGET;

    /**
     * `A_PosAttack`: one bullet
     */
    case POS_ATTACK;

    /**
     * `A_SPosAttack`: three pellets
     */
    case SPOS_ATTACK;

    /**
     * `A_TroopAttack`: a claw or a fireball
     */
    case TROOP_ATTACK;

    /**
     * `A_Fall`: the corpse stops blocking the way
     */
    case FALL;

    /**
     * `A_Explode`: the barrel hurts everything around it
     */
    case EXPLODE;
}
