<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The states of a state machine by their names, with the next state of each resolved once.
 *
 * @template A of \UnitEnum
 */
final readonly class StateTable
{
    /**
     * @param array<non-empty-string, StateData<A>> $states
     * @param array<non-empty-string, StateData<A>|null> $next
     */
    private function __construct(
        private array $states,
        private array $next,
    ) {}

    /**
     * @template E of \UnitEnum
     *
     * @param class-string<E> $actions the enum of the actions that the states run
     *
     * @return self<E>
     * @throws DataFileException in case of a state is invalid or refers to a missing state or action
     */
    public static function createFromRecord(DataRecord $record, string $file, string $actions): self
    {
        $states = [];

        foreach ($record->getRecords() as $name => $row) {
            $name = (string) $name;
            \assert($name !== '');

            $actionName = $row->getString('action');
            $action = null;

            if (\defined($actions . '::' . $actionName)) {
                $action = \constant($actions . '::' . $actionName);
            }

            if (!$action instanceof $actions) {
                throw DataFileException::becauseReferenceIsUnknown($file, $name . '.action', $actionName);
            }

            $states[$name] = new StateData(
                $name,
                $row->getString('sprite'),
                $row->getString('frame'),
                $row->findBool('fullBright'),
                $row->getInt('tics'),
                $row->findString('next'),
                $action,
            );
        }

        $next = [];

        foreach ($states as $name => $state) {
            $next[$name] = null;

            if ($state->nextName === null) {
                continue;
            }

            $next[$name] = $states[$state->nextName]
                ?? throw DataFileException::becauseReferenceIsUnknown($file, $name . '.next', $state->nextName);
        }

        return new self($states, $next);
    }

    /**
     * @return StateData<A>|null
     */
    public function find(string $name): ?StateData
    {
        return $this->states[$name] ?? null;
    }

    /**
     * @return StateData<A>
     * @throws DataFileException in case of the table has no such state
     */
    public function get(string $name): StateData
    {
        return $this->states[$name] ?? throw DataFileException::becauseReferenceIsUnknown('states', $name, $name);
    }

    /**
     * @param StateData<A> $state
     *
     * @return StateData<A>|null
     */
    public function getNext(StateData $state): ?StateData
    {
        return $this->next[$state->name] ?? null;
    }

    /**
     * @return list<StateData<A>>
     */
    public function getAll(): array
    {
        return \array_values($this->states);
    }
}
