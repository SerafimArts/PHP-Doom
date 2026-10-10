<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\Vertex;

/**
 * The rectangle that contains all the vertices of a level, in map units.
 */
final readonly class LevelBounds
{
    public function __construct(
        public int $minX,
        public int $minY,
        public int $maxX,
        public int $maxY,
    ) {}

    /**
     * @param list<Vertex> $vertexes the bounds of no vertexes is the point of the origin
     */
    public static function createFromVertexes(array $vertexes): self
    {
        $first = $vertexes[0] ?? new Vertex(0, 0);
        $minX = $maxX = $first->x;
        $minY = $maxY = $first->y;

        foreach ($vertexes as $vertex) {
            $minX = \min($minX, $vertex->x);
            $maxX = \max($maxX, $vertex->x);
            $minY = \min($minY, $vertex->y);
            $maxY = \max($maxY, $vertex->y);
        }

        return new self($minX, $minY, $maxX, $maxY);
    }
}
