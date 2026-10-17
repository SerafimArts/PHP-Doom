<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\Combat\PspriteAction;
use Serafim\Doom\Game\Weapon;
use Serafim\Doom\Level\Data\DataReader;

/**
 * The weapons of the player from `weapons.json`.
 */
final readonly class WeaponCatalog
{
    private const string FILE = 'weapons.json';

    /**
     * @param array<int, WeaponInfo> $weapons by the value of the {@see Weapon}
     */
    private function __construct(
        private array $weapons,
    ) {}

    /**
     * @param StateTable<PspriteAction> $states
     *
     * @throws DataFileException in case of the file is invalid or a weapon is missing
     */
    public static function createFromReader(DataReader $reader, StateTable $states): self
    {
        $records = $reader->read(self::FILE)->getRecords();
        $weapons = [];

        foreach (Weapon::cases() as $weapon) {
            $row = $records[$weapon->name]
                ?? throw DataFileException::becauseReferenceIsUnknown(self::FILE, $weapon->name, $weapon->name);

            $flash = null;

            if ($row->has('flash')) {
                $flash = $states->get($row->getString('flash'));
            }

            $weapons[$weapon->value] = new WeaponInfo(
                $weapon,
                $row->getString('sprite'),
                $row->findEnum('ammo', AmmoType::class),
                $states->get($row->getString('ready')),
                $states->get($row->getString('up')),
                $states->get($row->getString('down')),
                $states->get($row->getString('attack')),
                $flash,
            );
        }

        return new self($weapons);
    }

    public function get(Weapon $weapon): WeaponInfo
    {
        return $this->weapons[$weapon->value];
    }
}
