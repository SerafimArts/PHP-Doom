<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

use Serafim\Doom\Game\Physics\LineTable;
use Serafim\Doom\Level\LinedefFlag;
use Serafim\Doom\Level\SectorState;

/**
 * The noise of a shot reaches the sectors that connect to the sector of the shooter through openings. A line that
 * blocks sound lets the noise pass once on its way (`P_NoiseAlert` and `P_RecursiveSound`). A monster that stands in a
 * sector the noise reached wakes up.
 */
final class NoiseAlert
{
    /**
     * @var array<int, true>
     */
    private array $reached = [];

    public function __construct(
        private readonly LineTable $table,
        private readonly SectorState $sectors,
    ) {}

    /**
     * Spreads the noise of a shot made in a sector.
     */
    public function alert(int $sector): void
    {
        $traversed = [];

        $this->flood($sector, 0, $traversed);
    }

    public function isReached(int $sector): bool
    {
        return isset($this->reached[$sector]);
    }

    /**
     * @param array<int, int> $traversed the sound blocks plus one at which a sector was reached in this noise
     */
    private function flood(int $sector, int $blocks, array &$traversed): void
    {
        if (isset($traversed[$sector]) && $traversed[$sector] <= $blocks + 1) {
            return;
        }

        $traversed[$sector] = $blocks + 1;
        $this->reached[$sector] = true;

        foreach ($this->table->sectorLines[$sector] as $index) {
            $line = $this->table->lines[$index];
            \assert($line['back'] !== null);

            $openBottom = \max(
                $this->sectors->floorHeights[$line['front']],
                $this->sectors->floorHeights[$line['back']],
            );
            $openTop = \min(
                $this->sectors->ceilingHeights[$line['front']],
                $this->sectors->ceilingHeights[$line['back']],
            );

            if ($openTop <= $openBottom) {
                continue;
            }

            $other = $line['front'];

            if ($other === $sector) {
                $other = $line['back'];
            }

            if (($line['flags'] & LinedefFlag::BLOCK_SOUND) === 0) {
                $this->flood($other, $blocks, $traversed);

                continue;
            }

            if ($blocks === 0) {
                $this->flood($other, 1, $traversed);
            }
        }
    }
}
