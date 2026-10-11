<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The numbers that the renderer and the shaders share, from `resources/shaders/limits.json`. `build-shaders` writes the
 * same numbers into `limits.glsl`.
 */
final readonly class Limits
{
    public function __construct(
        /**
         * How many lights a frame holds
         *
         * @var int<1, max>
         */
        public int $maxLights,
        /**
         * The last row of the colormap texture
         *
         * @var int<0, max>
         */
        public int $colormapLastRow,
        public float $ticsPerSecond,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not a number
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $maxLights = $record->getInt('maxLights');
        $colormapLastRow = $record->getInt('colormapLastRow');

        if ($maxLights < 1) {
            throw DataFileException::becauseFieldIsInvalid('limits.json', 'maxLights', 'a positive integer');
        }

        if ($colormapLastRow < 0) {
            throw DataFileException::becauseFieldIsInvalid('limits.json', 'colormapLastRow', 'a natural number');
        }

        return new self($maxLights, $colormapLastRow, $record->getFloat('ticsPerSecond'));
    }
}
