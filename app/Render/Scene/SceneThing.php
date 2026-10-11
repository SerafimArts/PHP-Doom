<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Scene;

/**
 * A thing of the level as the renderer sees it: where it stands and which frame of which sprite it shows.
 */
final readonly class SceneThing
{
    public function __construct(
        /**
         * The number of the thing in the map (3004 is a zombieman)
         */
        public int $type,
        public float $x,
        public float $y,
        /**
         * Height of the feet
         */
        public float $z,
        /**
         * Counter-clockwise from the east, in radians
         */
        public float $angle,
        public float $radius,
        public float $height,
        /** @var non-empty-string */
        public string $sprite,
        /** @var non-empty-string */
        public string $frame,
        /**
         * Shown at full brightness whatever the light of the sector
         */
        public bool $isFullBright,
        /**
         * The light level of the sector the thing stands in
         */
        public int $light,
        /**
         * Players and monsters cannot walk through it
         */
        public bool $isSolid,
        public bool $isCorpse,
    ) {}
}
