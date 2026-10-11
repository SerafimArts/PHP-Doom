<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Layout;

/**
 * The struct `HudQuad` of `hud.vert`: a picture on the screen.
 */
final class QuadInstance
{
    /**
     * Bytes of one quad
     */
    public const int SIZE = 32;

    private function __construct() {}

    /**
     * @param int<0, max> $picture number of the picture in the atlas
     * @param int<0, max> $row row of the colormap that shades the picture
     */
    public static function pack(float $x, float $y, int $width, int $height, int $picture, int $row): string
    {
        return \pack('f4VlVV', $x, $y, $width, $height, $picture, $row, 0, 0);
    }
}
