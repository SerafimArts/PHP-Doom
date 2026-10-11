<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * How the lights of a frame look: the lamps of the level, the flash of a shot, the fireballs and the explosions, from
 * `lights.json`. A color is red, green, blue at the strength of 1.
 */
final readonly class LightProfile
{
    public function __construct(
        /** @var int<0, max> */
        public int $lampThing,
        public float $lampHeight,
        public float $lampRadius,
        /** @var array{float, float, float} */
        public array $lampColor,
        public float $flickerBase,
        public float $flickerAmplitude,
        public float $flickerSpeedA,
        public float $flickerSpeedB,
        public float $flickerPhase,
        public float $flashRadius,
        /** @var array{float, float, float} */
        public array $flashColor,
        /**
         * How far in front of the eye, to the right of it and above it the light of a shot sits: a light away from the
         * eye casts shadows that the player sees along the edges of what stands in the way
         */
        public float $flashForward,
        public float $flashSide,
        public float $flashUp,
        public float $flashStrengthFull,
        public float $flashStrengthPartial,
        public float $flashStrengthShort,
        /** @var array{float, float, float} */
        public array $fireColor,
        public FlameProfile $fireball,
        public FlameProfile $barrel,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $lamp = $record->getRecord('lamp');
        $flicker = $lamp->getRecord('flicker');
        $flash = $record->getRecord('flash');
        $strength = $flash->getRecord('strength');
        $thing = $lamp->getInt('thing');

        if ($thing < 0) {
            throw DataFileException::becauseFieldIsInvalid('lights.json', 'lamp.thing', 'a natural number');
        }

        return new self(
            $thing,
            $lamp->getFloat('height'),
            $lamp->getFloat('radius'),
            self::getColor($lamp, 'lamp.color'),
            $flicker->getFloat('base'),
            $flicker->getFloat('amplitude'),
            $flicker->getFloat('speedA'),
            $flicker->getFloat('speedB'),
            $flicker->getFloat('phase'),
            $flash->getFloat('radius'),
            self::getColor($flash, 'flash.color'),
            $flash->getFloat('forward'),
            $flash->getFloat('side'),
            $flash->getFloat('up'),
            $strength->getFloat('extraLight2'),
            $strength->getFloat('extraLight1'),
            $strength->getFloat('flashing'),
            self::getColor($record->getRecord('fire'), 'fire.color'),
            FlameProfile::createFromRecord($record->getRecord('fireball')),
            FlameProfile::createFromRecord($record->getRecord('barrel')),
        );
    }

    /**
     * @return array{float, float, float}
     * @throws DataFileException in case of the color is not three numbers
     */
    private static function getColor(DataRecord $record, string $path): array
    {
        $color = $record->getFloats('color');

        if (\count($color) !== 3) {
            throw DataFileException::becauseFieldIsInvalid('lights.json', $path, 'red, green and blue');
        }

        return [$color[0], $color[1], $color[2]];
    }
}
