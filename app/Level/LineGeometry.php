<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\MapData;

/**
 * Questions about a line of the map: on which side of it a point lies, where a segment crosses it and how wide the
 * opening between its two sectors is.
 */
final readonly class LineGeometry
{
    private const float PARALLEL = 1.0E-12;

    public function __construct(
        private MapData $map,
    ) {}

    /**
     * The point lies on the left side of the line, or on the line (`P_PointOnLineSide` is 1 or on it).
     *
     * @param int<0, max> $line
     */
    public function isOnLeft(int $line, float $x, float $y): bool
    {
        $linedef = $this->map->linedefs[$line];
        $from = $this->map->vertexes[$linedef->startVertex];
        $to = $this->map->vertexes[$linedef->endVertex];

        return ($to->x - $from->x) * ($y - $from->y) - ($to->y - $from->y) * ($x - $from->x) >= 0.0;
    }

    /**
     * Where a line is crossed by the segment: the fraction of the segment, {@see null} if the segment misses the line.
     *
     * @param int<0, max> $line
     */
    public function findCrossing(int $line, float $fromX, float $fromY, float $toX, float $toY): ?float
    {
        $linedef = $this->map->linedefs[$line];
        $start = $this->map->vertexes[$linedef->startVertex];
        $end = $this->map->vertexes[$linedef->endVertex];

        $lineX = $end->x - $start->x;
        $lineY = $end->y - $start->y;
        $moveX = $toX - $fromX;
        $moveY = $toY - $fromY;

        $denominator = $moveX * $lineY - $moveY * $lineX;

        if (\abs($denominator) < self::PARALLEL) {
            return null;
        }

        $alongMove = (($start->x - $fromX) * $lineY - ($start->y - $fromY) * $lineX) / $denominator;
        $alongLine = (($start->x - $fromX) * $moveY - ($start->y - $fromY) * $moveX) / $denominator;

        if ($alongMove < 0.0 || $alongMove > 1.0 || $alongLine < 0.0 || $alongLine > 1.0) {
            return null;
        }

        return $alongMove;
    }

    /**
     * The height of the opening between the two sectors of a line, {@see null} for a line with one side. A negative
     * number says that the floor of one sector is above the ceiling of the other.
     *
     * @param int<0, max> $line
     */
    public function getOpening(int $line, SectorState $sectors): ?float
    {
        $linedef = $this->map->linedefs[$line];

        if ($linedef->leftSidedef === null) {
            return null;
        }

        $front = $this->map->sidedefs[$linedef->rightSidedef]->sector;
        $back = $this->map->sidedefs[$linedef->leftSidedef]->sector;

        $top = \min($sectors->ceilingHeights[$front], $sectors->ceilingHeights[$back]);
        $bottom = \max($sectors->floorHeights[$front], $sectors->floorHeights[$back]);

        return (float) ($top - $bottom);
    }
}
