<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The positions of the pieces of the status bar on the screen of 320 x 200 points (`ST_Drawer`), from
 * `hud/statusbar.json`.
 */
final readonly class StatusBarLayout
{
    public function __construct(
        public int $barTop,
        /**
         * Width of a letter that the font does not have
         */
        public int $spaceWidth,
        public string $armsPicture,
        public int $armsX,
        public int $faceX,
        public NumberLayout $ammo,
        public NumberLayout $health,
        public NumberLayout $armor,
        public WeaponGridLayout $weapons,
        public AmmoRowsLayout $ammoRows,
        /**
         * How the light of a sector turns into the colormap row of the weapon in the hands
         */
        public int $weaponLightLevels,
        public int $weaponLightRowsPerLevel,
        public int $weaponLightRowOffset,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $arms = $record->getRecord('arms');
        $light = $record->getRecord('weaponLight');

        return new self(
            $record->getInt('barTop'),
            $record->getInt('spaceWidth'),
            $arms->getString('picture'),
            $arms->getInt('x'),
            $record->getRecord('face')->getInt('x'),
            NumberLayout::createFromRecord($record->getRecord('ammo')),
            NumberLayout::createFromRecord($record->getRecord('health')),
            NumberLayout::createFromRecord($record->getRecord('armor')),
            WeaponGridLayout::createFromRecord($record->getRecord('weapons')),
            AmmoRowsLayout::createFromRecord($record->getRecord('ammoRows')),
            $light->getInt('levels'),
            $light->getInt('rowsPerLevel'),
            $light->getInt('rowOffset'),
        );
    }
}
