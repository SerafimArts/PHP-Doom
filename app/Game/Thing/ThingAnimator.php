<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

use Serafim\Doom\Game\Mobj;

/**
 * Moves the things through their states. Puffs rise and blood falls.
 */
final readonly class ThingAnimator
{
    public function __construct(
        private MobjStates $states,
    ) {}

    /**
     * @param list<Mobj> $things
     */
    public function animate(array $things): void
    {
        foreach ($things as $thing) {
            if ($thing->isRemoved) {
                continue;
            }

            $this->move($thing);
            $this->states->tick($thing);
        }
    }

    private function move(Mobj $thing): void
    {
        if ($thing->momentumZ === 0.0 || $thing->missile?->isFlying === true) {
            return;
        }

        $thing->z += $thing->momentumZ;

        $thing->momentumZ -= $thing->definition->gravity ?? 0.0;

        if ($thing->z <= $thing->floorZ) {
            $thing->z = $thing->floorZ;
            $thing->momentumZ = 0.0;
        }
    }
}
