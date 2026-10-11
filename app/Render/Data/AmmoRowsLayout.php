<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;
use Serafim\Doom\Render\Hud\HudAmmo;

/**
 * The small table of the current and the maximal ammunition on the arms panel.
 */
final readonly class AmmoRowsLayout
{
    public function __construct(
        public string $prefix,
        public int $currentRight,
        public int $capacityRight,
        public int $y,
        public int $rowHeight,
        /** @var int<1, max> */
        public int $digitWidth,
        /** @var list<AmmoRow> */
        public array $rows,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $digitWidth = $record->getInt('digitWidth');

        if ($digitWidth < 1) {
            throw DataFileException::becauseFieldIsInvalid(
                'hud/statusbar.json',
                'ammoRows.digitWidth',
                'a positive integer',
            );
        }

        $rows = [];

        foreach ($record->getRecord('rows')->getRecords() as $row) {
            $rows[] = new AmmoRow($row->findEnum('ammo', HudAmmo::class), $row->findInt('capacity', 0));
        }

        return new self(
            $record->getString('prefix'),
            $record->getInt('currentRight'),
            $record->getInt('capacityRight'),
            $record->getInt('y'),
            $record->getInt('rowHeight'),
            $digitWidth,
            $rows,
        );
    }
}
