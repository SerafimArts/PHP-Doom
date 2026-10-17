<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * Where the eyes of the player are and where they look.
 */
final readonly class PlayerView
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
        /**
         * Counter-clockwise from the east, in radians
         */
        public float $yaw,
    ) {}

    /**
     * The view between this one and the next one, `$alpha` is from 0 to 1.
     */
    public function interpolate(self $next, float $alpha): self
    {
        return new self(
            $this->x + ($next->x - $this->x) * $alpha,
            $this->y + ($next->y - $this->y) * $alpha,
            $this->z + ($next->z - $this->z) * $alpha,
            $this->yaw + ($next->yaw - $this->yaw) * $alpha,
        );
    }
}
