<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * Where a number of the status bar ends and how its digits look, with the percent sign that may follow it.
 */
final readonly class NumberLayout
{
    public function __construct(
        public string $prefix,
        /**
         * The x that the last digit ends at
         */
        public int $right,
        public int $y,
        /** @var int<1, max> */
        public int $digitWidth,
        public ?PercentSign $percent = null,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $digitWidth = $record->getInt('digitWidth');

        if ($digitWidth < 1) {
            throw DataFileException::becauseFieldIsInvalid('hud/statusbar.json', 'digitWidth', 'a positive integer');
        }

        $percent = null;

        if ($record->has('percent')) {
            $sign = $record->getRecord('percent');
            $percent = new PercentSign($sign->getString('picture'), $sign->getInt('x'), $sign->getInt('y'));
        }

        return new self(
            $record->getString('prefix'),
            $record->getInt('right'),
            $record->getInt('y'),
            $digitWidth,
            $percent,
        );
    }
}
