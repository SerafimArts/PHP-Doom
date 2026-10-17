<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * The things that stand in the level. The ones that were picked up or destroyed are marked as removed and leave the
 * list at the end of a tic.
 *
 * @implements \IteratorAggregate<int, Mobj>
 */
final class ThingRegistry implements \IteratorAggregate, \Countable
{
    /**
     * @var list<Mobj>
     */
    private array $things = [];

    /**
     * @param list<Mobj> $things
     */
    public function __construct(array $things = [])
    {
        $this->things = $things;
    }

    public function add(Mobj $thing): void
    {
        $this->things[] = $thing;
    }

    /**
     * @param list<Mobj> $things
     */
    public function replace(array $things): void
    {
        $this->things = $things;
    }

    /**
     * @return list<Mobj>
     */
    public function all(): array
    {
        return $this->things;
    }

    /**
     * Drops the things that are marked as removed.
     */
    public function removeFinished(): void
    {
        $this->things = \array_values(\array_filter(
            $this->things,
            static fn(Mobj $thing): bool => !$thing->isRemoved,
        ));
    }

    /**
     * @return \ArrayIterator<int, Mobj>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->things);
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->things);
    }
}
