<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

use Serafim\Doom\Game\Data\StateData;

/**
 * A sprite of the player drawn on the screen: the weapon or its muzzle flash (`pspdef_t`).
 */
final class Psprite
{
    /**
     * {@see null} when nothing is shown
     *
     * @var StateData<PspriteAction>|null
     */
    public ?StateData $state = null;

    /**
     * Tics left in the state
     */
    public int $tics = 0;

    /**
     * Position on the screen of 320 x 200 points: the weapon is raised at the height 32 and put down at 128
     */
    public float $x = 0.0;

    public float $y = 0.0;
}
