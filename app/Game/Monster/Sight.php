<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

use Serafim\Doom\Game\Data\Tuning\MonsterTuning;
use Serafim\Doom\Game\Physics\LineTable;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;

/**
 * Tells whether a thing can see another: the line of sight is cut by the reject table of the map, by walls and by the
 * edges of the openings that it does not pass through (`P_CheckSight`).
 */
final readonly class Sight
{
    /**
     * Height of the eyes of a monster as a part of its height
     */
    private int $sectorCount;

    public function __construct(
        private Level $level,
        private SectorState $sectors,
        private LineTable $table,
        private MonsterTuning $tuning,
    ) {
        $this->sectorCount = \count($level->map->sectors);
    }

    /**
     * @param float $z the height of the feet of the one that looks
     */
    public function canSee(
        float $fromX,
        float $fromY,
        float $z,
        float $height,
        float $toX,
        float $toY,
        float $toZ,
        float $toHeight,
    ): bool {
        if ($this->isRejected((int) \floor($fromX), (int) \floor($fromY), (int) \floor($toX), (int) \floor($toY))) {
            return false;
        }

        $dx = $toX - $fromX;
        $dy = $toY - $fromY;

        $eye = $z + $height * $this->tuning->eyePart;
        $top = $toZ + $toHeight - $eye;
        $bottom = $toZ - $eye;

        $candidates = $this->level->lineGrid->findLines(
            \min($fromX, $toX),
            \max($fromX, $toX),
            \min($fromY, $toY),
            \max($fromY, $toY),
        );

        foreach ($candidates as $index) {
            $line = $this->table->lines[$index];

            $fraction = $this->findCrossing($line, $fromX, $fromY, $dx, $dy);

            if ($fraction === null) {
                continue;
            }

            if ($line['back'] === null) {
                return false;
            }

            $frontFloor = $this->sectors->floorHeights[$line['front']];
            $backFloor = $this->sectors->floorHeights[$line['back']];
            $frontCeiling = $this->sectors->ceilingHeights[$line['front']];
            $backCeiling = $this->sectors->ceilingHeights[$line['back']];

            $openBottom = \max($frontFloor, $backFloor);
            $openTop = \min($frontCeiling, $backCeiling);

            if ($openBottom >= $openTop) {
                return false;
            }

            if ($frontFloor !== $backFloor) {
                $bottom = \max($bottom, ($openBottom - $eye) / $fraction);
            }

            if ($frontCeiling !== $backCeiling) {
                $top = \min($top, ($openTop - $eye) / $fraction);
            }

            if ($top <= $bottom) {
                return false;
            }
        }

        return true;
    }

    /**
     * The reject table says for a pair of sectors that nothing in one of them can see anything in the other.
     */
    private function isRejected(int $fromX, int $fromY, int $toX, int $toY): bool
    {
        $reject = $this->level->map->reject;

        if ($reject === '') {
            return false;
        }

        $bit = $this->level->findSector($fromX, $fromY) * $this->sectorCount + $this->level->findSector($toX, $toY);
        $byte = $bit >> 3;

        if ($byte >= \strlen($reject)) {
            return false;
        }

        return ((\ord($reject[$byte]) >> ($bit & 7)) & 1) === 1;
    }

    /**
     * Where the sight line crosses a line: the fraction of the sight line, {@see null} when it does not cross.
     *
     * @param array{x: float, y: float, dx: float, dy: float, front: int, back: int|null, flags: int} $line
     */
    private function findCrossing(array $line, float $fromX, float $fromY, float $dx, float $dy): ?float
    {
        $denominator = $dx * $line['dy'] - $dy * $line['dx'];

        if ($denominator === 0.0) {
            return null;
        }

        $fromLineX = $line['x'] - $fromX;
        $fromLineY = $line['y'] - $fromY;

        $fraction = ($fromLineX * $line['dy'] - $fromLineY * $line['dx']) / $denominator;
        $along = ($fromLineX * $dy - $fromLineY * $dx) / $denominator;

        if ($fraction <= 0.0 || $fraction >= 1.0 || $along < 0.0 || $along > 1.0) {
            return null;
        }

        return $fraction;
    }
}
