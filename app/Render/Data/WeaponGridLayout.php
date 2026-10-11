<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The numbers of the weapons on the arms panel: owned ones are yellow, the others gray.
 */
final readonly class WeaponGridLayout
{
    public function __construct(
        public int $first,
        public int $last,
        public int $x,
        public int $y,
        /** @var int<1, max> */
        public int $columns,
        public int $cellWidth,
        public int $cellHeight,
        public string $ownedPrefix,
        public string $missingPrefix,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $columns = $record->getInt('columns');

        if ($columns < 1) {
            throw DataFileException::becauseFieldIsInvalid(
                'hud/statusbar.json',
                'weapons.columns',
                'a positive integer',
            );
        }

        return new self(
            $record->getInt('first'),
            $record->getInt('last'),
            $record->getInt('x'),
            $record->getInt('y'),
            $columns,
            $record->getInt('cellWidth'),
            $record->getInt('cellHeight'),
            $record->getString('ownedPrefix'),
            $record->getString('missingPrefix'),
        );
    }
}
