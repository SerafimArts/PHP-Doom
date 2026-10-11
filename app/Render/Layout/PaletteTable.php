<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Layout;

use Local\WAD\Image\Palette;

/**
 * The palettes of the game as the rows of a texture of RGBA texels.
 */
final class PaletteTable
{
    private function __construct() {}

    /**
     * @param list<Palette> $palettes
     */
    public static function pack(array $palettes): string
    {
        $data = '';

        foreach ($palettes as $palette) {
            foreach (\str_split($palette->rgb, 3) as $color) {
                $data .= $color . "\xFF";
            }
        }

        return $data;
    }
}
