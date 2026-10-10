<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\Blockmap;
use Local\WAD\Map\MapData;

/**
 * Finds the lines near a place with the blockmap: the lines of every block of 128 x 128 units are listed in the map.
 */
final readonly class LineGrid
{
    public function __construct(
        private MapData $map,
    ) {}

    /**
     * The lines of the blocks that the rectangle touches, every line once.
     *
     * @return list<int<0, max>>
     */
    public function findLines(float $left, float $right, float $bottom, float $top): array
    {
        $blockmap = $this->map->blockmap;

        if ($blockmap === null || $blockmap->columns === 0 || $blockmap->rows === 0) {
            return \array_keys($this->map->linedefs);
        }

        $size = Blockmap::BLOCK_SIZE;
        $firstColumn = \max(0, (int) \floor(($left - $blockmap->originX) / $size));
        $lastColumn = \min($blockmap->columns - 1, (int) \floor(($right - $blockmap->originX) / $size));
        $firstRow = \max(0, (int) \floor(($bottom - $blockmap->originY) / $size));
        $lastRow = \min($blockmap->rows - 1, (int) \floor(($top - $blockmap->originY) / $size));

        $found = [];

        for ($row = $firstRow; $row <= $lastRow; ++$row) {
            for ($column = $firstColumn; $column <= $lastColumn; ++$column) {
                foreach ($blockmap->blocks[$row * $blockmap->columns + $column] ?? [] as $index) {
                    $found[$index] = $index;
                }
            }
        }

        return \array_values($found);
    }
}
