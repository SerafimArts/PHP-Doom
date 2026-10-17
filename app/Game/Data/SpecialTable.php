<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataReader;
use Serafim\Doom\Level\SectorSpecial;

/**
 * The numbers of the sector and line specials (`p_spec.c`, `p_doors.c`, `p_plats.c`, `p_lights.c`), from
 * `specials.json`.
 */
final readonly class SpecialTable
{
    private const string FILE = 'specials.json';

    /**
     * @param array<int, int> $sectorDamage the damage of a tic of the floor by the number of the sector special
     */
    public function __construct(
        private array $sectorDamage,
        public float $doorSpeed,
        /** @var int<0, max> */
        public int $doorWait,
        public float $doorClearance,
        public float $platformSpeed,
        /** @var int<0, max> */
        public int $platformWait,
        public float $floorSpeed,
        public int $flashBrightMask,
        public int $flashDarkMask,
        /** @var int<1, max> */
        public int $strobeBrightTics,
        /** @var int<1, max> */
        public int $strobeSlowDarkTics,
        /** @var int<1, max> */
        public int $glowSpeed,
    ) {}

    /**
     * @throws DataFileException in case of the file is invalid
     */
    public static function createFromReader(DataReader $reader): self
    {
        $record = $reader->read(self::FILE);

        $damage = [];

        foreach ($record->getRecord('sectorDamage')->getRecords() as $row) {
            $damage[$row->getInt('special')] = $row->getInt('damage');
        }

        $door = $record->getRecord('door');
        $platform = $record->getRecord('platform');
        $flash = $record->getRecord('lightFlash');
        $strobe = $record->getRecord('strobe');

        return new self(
            $damage,
            $door->getFloat('speed'),
            \max(0, $door->getInt('wait')),
            $door->getFloat('clearance'),
            $platform->getFloat('speed'),
            \max(0, $platform->getInt('wait')),
            $record->getRecord('floor')->getFloat('speed'),
            $flash->getInt('brightMask'),
            $flash->getInt('darkMask'),
            \max(1, $strobe->getInt('brightTics')),
            \max(1, $strobe->getInt('slowDarkTics')),
            \max(1, $record->getRecord('glow')->getInt('speed')),
        );
    }

    /**
     * Damage to the player standing on the floor of a sector with the special, every 32 tics.
     */
    public function getSectorDamage(SectorSpecial $special): int
    {
        return $this->sectorDamage[$special->value] ?? 0;
    }
}
