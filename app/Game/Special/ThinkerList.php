<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * The thinkers of a level. A thinker that moves a sector holds the sector: nothing else moves it meanwhile.
 */
final class ThinkerList
{
    /** @var list<Thinker> */
    private array $thinkers = [];

    /** @var array<int, Thinker> */
    private array $held = [];

    public function add(Thinker $thinker, ?int $holdSector = null): void
    {
        $this->thinkers[] = $thinker;

        if ($holdSector !== null) {
            $this->held[$holdSector] = $thinker;
        }
    }

    public function findHolder(int $sector): ?Thinker
    {
        return $this->held[$sector] ?? null;
    }

    public function release(int $sector): void
    {
        unset($this->held[$sector]);
    }

    public function run(): void
    {
        $alive = [];

        foreach ($this->thinkers as $thinker) {
            if ($thinker->think()) {
                $alive[] = $thinker;
            }
        }

        $this->thinkers = $alive;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->thinkers);
    }
}
