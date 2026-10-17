<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

/**
 * What a state of the weapon in the hands does when the weapon enters it (the `A_*` functions of `p_pspr.c`).
 */
enum PspriteAction
{
    case NONE;

    /**
     * `A_WeaponReady`
     */
    case READY;

    /**
     * `A_Lower`
     */
    case LOWER;

    /**
     * `A_Raise`
     */
    case RAISE;

    /**
     * `A_Punch`
     */
    case PUNCH;

    /**
     * `A_FirePistol`
     */
    case FIRE_PISTOL;

    /**
     * `A_FireShotgun`
     */
    case FIRE_SHOTGUN;

    /**
     * `A_ReFire`
     */
    case REFIRE;

    /**
     * `A_Light0`: the muzzle flash is gone
     */
    case LIGHT0;

    /**
     * `A_Light1`
     */
    case LIGHT1;

    /**
     * `A_Light2`
     */
    case LIGHT2;

    /**
     * The light that a muzzle flash adds to the scene while the state lasts, {@see null} for an action that does not
     * touch it.
     *
     * @return int<0, 2>|null
     */
    public function getExtraLight(): ?int
    {
        return match ($this) {
            self::LIGHT0 => 0,
            self::LIGHT1 => 1,
            self::LIGHT2 => 2,
            default => null,
        };
    }
}
