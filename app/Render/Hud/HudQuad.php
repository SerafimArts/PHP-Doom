<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Hud;

use Serafim\Doom\Render\Layout\QuadInstance;

/**
 * A picture on the screen of 320 x 200 points.
 */
final readonly class HudQuad
{
    public function __construct(
        public float $x,
        public float $y,
        public int $width,
        public int $height,
        /**
         * The number of the picture in the atlas
         *
         * @var int<0, max>
         */
        public int $picture,
        /**
         * The row of the colormap that shades the picture, 0 is the brightest
         *
         * @var int<0, max>
         */
        public int $row = 0,
    ) {}

    public function pack(): string
    {
        return QuadInstance::pack($this->x, $this->y, $this->width, $this->height, $this->picture, $this->row);
    }
}
