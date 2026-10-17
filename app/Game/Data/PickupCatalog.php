<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\ArmorType;
use Serafim\Doom\Game\Weapon;
use Serafim\Doom\Level\Data\DataReader;

/**
 * The items that the player picks up, from `pickups.json`.
 */
final readonly class PickupCatalog
{
    private const string FILE = 'pickups.json';

    /**
     * @param array<string, PickupDefinition> $pickups
     */
    private function __construct(
        private array $pickups,
    ) {}

    /**
     * @throws DataFileException in case of the file is invalid
     */
    public static function createFromReader(DataReader $reader): self
    {
        $pickups = [];

        foreach ($reader->read(self::FILE)->getRecords() as $name => $row) {
            $name = (string) $name;
            \assert($name !== '');

            $pickups[$name] = new PickupDefinition(
                $name,
                $row->getEnumByValue('kind', PickupKind::class),
                $row->getString('message'),
                $row->findEnum('ammo', AmmoType::class),
                $row->findInt('amount', 0),
                $row->findInt('limit', 0),
                $row->findBool('halvedWhenDropped'),
                $row->findEnum('armor', ArmorType::class),
                $row->findEnum('weapon', Weapon::class),
                $row->findInt('needBelow', 0),
                $row->findString('needMessage'),
                $row->findString('newMessage'),
            );
        }

        return new self($pickups);
    }

    /**
     * @throws DataFileException in case of there is no such item
     */
    public function get(string $name): PickupDefinition
    {
        return $this->pickups[$name] ?? throw DataFileException::becauseReferenceIsUnknown(self::FILE, $name, $name);
    }

    /**
     * @return array<string, PickupDefinition>
     */
    public function getAll(): array
    {
        return $this->pickups;
    }
}
