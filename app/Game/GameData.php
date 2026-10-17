<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Game\Combat\PspriteAction;
use Serafim\Doom\Game\Data\PickupCatalog;
use Serafim\Doom\Game\Data\SpecialTable;
use Serafim\Doom\Game\Data\StateTable;
use Serafim\Doom\Game\Data\Tuning;
use Serafim\Doom\Game\Data\WeaponCatalog;
use Serafim\Doom\Game\Thing\StateAction;
use Serafim\Doom\Game\Thing\ThingCatalog;
use Serafim\Doom\Level\Data\AnimationTable;
use Serafim\Doom\Level\Data\DataReader;

/**
 * All the tables of the game that come from the data files (`resources/data`), loaded once.
 */
final readonly class GameData
{
    private const string MOBJ_STATES_FILE = 'mobj-states.json';

    private const string WEAPON_STATES_FILE = 'weapon-states.json';

    public function __construct(
        /** @var StateTable<StateAction> */
        public StateTable $mobjStates,
        /** @var StateTable<PspriteAction> */
        public StateTable $weaponStates,
        public WeaponCatalog $weapons,
        public ThingCatalog $things,
        public PickupCatalog $pickups,
        public AnimationTable $animations,
        public SpecialTable $specials,
        public Tuning $tuning,
    ) {}

    /**
     * Loads the tables from the data directory of the project, or from the given one.
     *
     * @throws DataFileException in case of a file is missing or invalid
     */
    public static function load(?DataReader $reader = null): self
    {
        $reader ??= DataReader::createDefault();

        $mobjStates = StateTable::createFromRecord(
            $reader->read(self::MOBJ_STATES_FILE),
            self::MOBJ_STATES_FILE,
            StateAction::class,
        );
        $weaponStates = StateTable::createFromRecord(
            $reader->read(self::WEAPON_STATES_FILE),
            self::WEAPON_STATES_FILE,
            PspriteAction::class,
        );
        $pickups = PickupCatalog::createFromReader($reader);

        return new self(
            $mobjStates,
            $weaponStates,
            WeaponCatalog::createFromReader($reader, $weaponStates),
            ThingCatalog::createFromReader($reader, $mobjStates, $pickups),
            $pickups,
            AnimationTable::createFromReader($reader),
            SpecialTable::createFromReader($reader),
            Tuning::createFromReader($reader),
        );
    }
}
