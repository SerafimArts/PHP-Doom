<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * The player: a vertical cylinder that stands on the floor. Positions are in map units, speeds in units per tic.
 */
final class Player
{
    public const float RADIUS = 16.0;

    public const float HEIGHT = 56.0;

    public const float EYE_HEIGHT = 41.0;

    public float $momentumX = 0.0;

    public float $momentumY = 0.0;

    public float $momentumZ = 0.0;

    /**
     * Floor and ceiling of the place the player stands in
     */
    public float $floorZ;

    public float $ceilingZ;

    /**
     * Swing of the view while walking
     */
    public float $bob = 0.0;

    /**
     * Height of the eyes over the feet, a dead player's view sinks to the floor
     */
    public float $viewHeight = self::EYE_HEIGHT;

    public function __construct(
        public float $x,
        public float $y,
        public float $z,
        public float $yaw,
    ) {
        $this->floorZ = $z;
        $this->ceilingZ = $z + self::HEIGHT;
    }

    public function isOnGround(): bool
    {
        return $this->z <= $this->floorZ;
    }

    public function getView(int $tic): PlayerView
    {
        $swing = 0.0;

        if ($this->isOnGround() && $this->viewHeight >= self::EYE_HEIGHT) {
            $swing = $this->bob / 2 * \sin(2 * \M_PI * $tic / 20);
        }

        return new PlayerView($this->x, $this->y, $this->z + $this->viewHeight + $swing, $this->yaw);
    }
}
