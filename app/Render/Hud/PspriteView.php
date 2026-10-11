<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Hud;

/**
 * A weapon or its flash in the hands of the player, where its state puts it on the screen of 320 x 200 points.
 */
final readonly class PspriteView
{
    public function __construct(
        /** @var non-empty-string */
        public string $sprite,
        /** @var non-empty-string */
        public string $frame,
        /**
         * Shown at full brightness whatever the light of the sector
         */
        public bool $isFullBright,
        public float $x,
        public float $y,
    ) {}
}
