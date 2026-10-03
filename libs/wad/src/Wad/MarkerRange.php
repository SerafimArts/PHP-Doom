<?php

declare(strict_types=1);

namespace Local\WAD\Wad;

/**
 * The pairs of marker lumps that enclose a group of lumps: the sprites and the flats. A WAD of Doom 1 and the later
 * ones use the first pair, a PWAD may use the second.
 */
enum MarkerRange
{
    case SPRITES;

    case FLATS;

    /**
     * @return array{non-empty-string, non-empty-string} the names of the first and of the last marker
     */
    public function getMarkers(): array
    {
        return match ($this) {
            self::SPRITES => ['S_START', 'S_END'],
            self::FLATS => ['F_START', 'F_END'],
        };
    }

    /**
     * @return array{non-empty-string, non-empty-string} the markers of a PWAD
     */
    public function getPwadMarkers(): array
    {
        return match ($this) {
            self::SPRITES => ['SS_START', 'SS_END'],
            self::FLATS => ['FF_START', 'FF_END'],
        };
    }
}
