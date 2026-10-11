<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Render\Hud\HudAmmo;

/**
 * A row of the ammunition table on the arms panel. A row of an ammunition that the game does not have shows none and
 * the capacity of the original.
 */
final readonly class AmmoRow
{
    public function __construct(
        public ?HudAmmo $ammo,
        public int $capacity = 0,
    ) {}
}
