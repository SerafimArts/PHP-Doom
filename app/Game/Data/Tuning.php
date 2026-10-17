<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Game\Data\Tuning\CombatTuning;
use Serafim\Doom\Game\Data\Tuning\MonsterTuning;
use Serafim\Doom\Game\Data\Tuning\PhysicsTuning;
use Serafim\Doom\Game\Data\Tuning\WeaponTuning;
use Serafim\Doom\Level\Data\DataReader;

/**
 * The numbers that tune the physics and the fights, from `tuning.json`.
 */
final readonly class Tuning
{
    private const string FILE = 'tuning.json';

    public function __construct(
        public PhysicsTuning $physics,
        public WeaponTuning $weapon,
        public CombatTuning $combat,
        public MonsterTuning $monster,
    ) {}

    /**
     * @throws DataFileException in case of the file is invalid
     */
    public static function createFromReader(DataReader $reader): self
    {
        $record = $reader->read(self::FILE);

        return new self(
            PhysicsTuning::createFromRecord($record->getRecord('physics')),
            WeaponTuning::createFromRecord($record->getRecord('weapon')),
            CombatTuning::createFromRecord($record->getRecord('combat')),
            MonsterTuning::createFromRecord($record->getRecord('monster')),
        );
    }
}
