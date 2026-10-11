<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The positions and the pictures of the screen after the exit of a level (`WI_drawStats`), from
 * `hud/intermission.json`.
 */
final readonly class IntermissionLayout
{
    public function __construct(
        public int $screenWidth,
        public int $titleY,
        public int $statsX,
        public int $statsY,
        public int $timeX,
        public int $timeY,
        /**
         * Digits of a minute or of a second
         *
         * @var int<1, max>
         */
        public int $timeDigits,
        /**
         * What a time over this many seconds shows instead of the number
         */
        public int $longestTime,
        public string $background,
        public string $title,
        public string $finished,
        public string $kills,
        public string $items,
        public string $secrets,
        public string $time,
        public string $par,
        public string $percent,
        public string $colon,
        public string $sucks,
        public string $digitPrefix,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $timeDigits = $record->getInt('timeDigits');

        if ($timeDigits < 1) {
            throw DataFileException::becauseFieldIsInvalid(
                'hud/intermission.json',
                'timeDigits',
                'a positive integer',
            );
        }

        return new self(
            $record->getInt('screenWidth'),
            $record->getInt('titleY'),
            $record->getInt('statsX'),
            $record->getInt('statsY'),
            $record->getInt('timeX'),
            $record->getInt('timeY'),
            $timeDigits,
            $record->getInt('longestTime'),
            $record->getString('background'),
            $record->getString('title'),
            $record->getString('finished'),
            $record->getString('kills'),
            $record->getString('items'),
            $record->getString('secrets'),
            $record->getString('time'),
            $record->getString('par'),
            $record->getString('percent'),
            $record->getString('colon'),
            $record->getString('sucks'),
            $record->getString('digitPrefix'),
        );
    }
}
