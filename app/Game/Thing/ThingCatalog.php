<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Game\Data\PickupCatalog;
use Serafim\Doom\Game\Data\StateData;
use Serafim\Doom\Game\Data\StateTable;
use Serafim\Doom\Level\Data\DataReader;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The kinds of the things of the level, from `things.json` (`mobjinfo`).
 */
final readonly class ThingCatalog
{
    private const string FILE = 'things.json';

    /**
     * @param array<int, ThingDefinition> $byType
     * @param array<string, ThingDefinition> $byName
     */
    private function __construct(
        private array $byType,
        private array $byName,
    ) {}

    /**
     * @param StateTable<StateAction> $states
     *
     * @throws DataFileException in case of the file is invalid or a thing refers to a missing state, item or thing
     */
    public static function createFromReader(DataReader $reader, StateTable $states, PickupCatalog $pickups): self
    {
        $byType = [];
        $byName = [];
        $pseudoType = 0;

        foreach ($reader->read(self::FILE)->getRecords() as $name => $row) {
            $name = (string) $name;
            \assert($name !== '');

            $type = $row->findInt('doomednum', 0);

            if ($type === 0) {
                $type = --$pseudoType;
            }

            $pickup = null;

            if ($row->has('pickup')) {
                $pickup = $pickups->get($row->getString('pickup'));
            }

            $definition = new ThingDefinition(
                $type,
                $name,
                $row->getFloat('radius'),
                $row->getFloat('height'),
                $states->get($row->getString('spawn')),
                $row->findBool('solid'),
                $pickup,
                $row->findInt('health', 0),
                $row->findInt('painChance', 0),
                self::findState($row, 'pain', $states),
                self::findState($row, 'death', $states),
                self::findState($row, 'xdeath', $states),
                $row->findBool('shootable'),
                $row->findBool('blood', true),
                $row->findBool('countsAsKill'),
                $row->findString('drop'),
                $row->findFloat('speed', 0.0),
                $row->findInt('reactionTime', 0),
                self::findState($row, 'see', $states),
                self::findState($row, 'melee', $states),
                self::findState($row, 'missile', $states),
                $row->findBool('isMissile'),
                $row->findInt('damage', 0),
                $row->findBool('countsAsItem'),
                $row->findBool('randomStart'),
                $row->findFloat('gravity', 0.0),
            );

            $byType[$type] = $definition;
            $byName[$name] = $definition;
        }

        foreach ($byName as $name => $definition) {
            if ($definition->dropName !== null && !isset($byName[$definition->dropName])) {
                throw DataFileException::becauseReferenceIsUnknown(self::FILE, $name . '.drop', $definition->dropName);
            }
        }

        return new self($byType, $byName);
    }

    /**
     * The kind of the things with the number in the map, {@see null} for a number that the game does not know.
     */
    public function findByType(int $type): ?ThingDefinition
    {
        return $this->byType[$type] ?? null;
    }

    /**
     * @throws DataFileException in case of there is no such thing
     */
    public function getByName(string $name): ThingDefinition
    {
        return $this->byName[$name] ?? throw DataFileException::becauseReferenceIsUnknown(self::FILE, $name, $name);
    }

    /**
     * @return array<string, ThingDefinition>
     */
    public function getAll(): array
    {
        return $this->byName;
    }

    /**
     * @param StateTable<StateAction> $states
     *
     * @return StateData<StateAction>|null
     */
    private static function findState(DataRecord $row, string $key, StateTable $states): ?StateData
    {
        $name = $row->findString($key);

        if ($name === null) {
            return null;
        }

        return $states->get($name);
    }
}
