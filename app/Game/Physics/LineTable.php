<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Physics;

use Serafim\Doom\Level\Level;

/**
 * The lines of a level in the form that the sight and the sound travel along: the ends, the sectors on both sides and
 * the flags.
 */
final readonly class LineTable
{
    /**
     * @var list<array{x: float, y: float, dx: float, dy: float, front: int, back: int|null, flags: int}>
     */
    public array $lines;

    /**
     * The two-sided lines of every sector
     *
     * @var array<int, list<int>>
     */
    public array $sectorLines;

    public function __construct(
        public Level $level,
    ) {
        $map = $level->map;
        $lines = [];
        $sectorLines = \array_fill(0, \count($map->sectors), []);

        foreach ($map->linedefs as $index => $linedef) {
            $from = $map->vertexes[$linedef->startVertex];
            $to = $map->vertexes[$linedef->endVertex];
            $back = null;

            if ($linedef->leftSidedef !== null) {
                $back = $map->sidedefs[$linedef->leftSidedef]->sector;
            }

            $front = $map->sidedefs[$linedef->rightSidedef]->sector;

            $lines[] = [
                'x' => (float) $from->x,
                'y' => (float) $from->y,
                'dx' => (float) ($to->x - $from->x),
                'dy' => (float) ($to->y - $from->y),
                'front' => $front,
                'back' => $back,
                'flags' => $linedef->flags,
            ];

            if ($back !== null) {
                $sectorLines[$front][] = $index;
                $sectorLines[$back][] = $index;
            }
        }

        $this->lines = $lines;
        $this->sectorLines = $sectorLines;
    }
}
