<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Geometry;

/**
 * One textured part of a wall: the upper, lower or middle texture of one side of a seg. The heights of the part are
 * not stored, they depend on the sectors that move (doors, lifts) and are computed from the sector table.
 */
final readonly class WallPart
{
    public function __construct(
        /** @var int<0, max> */
        public int $seg,
        /**
         * The sidedef that holds the texture
         *
         * @var int<0, max>
         */
        public int $sidedef,
        public WallPartType $type,
        public Point $start,
        public Point $end,
        /**
         * The sector the seg faces
         *
         * @var int<0, max>
         */
        public int $frontSector,
        /**
         * The sector behind the seg, {@see null} for a one-sided wall
         *
         * @var int<0, max>|null
         */
        public ?int $backSector,
        /** @var non-empty-string */
        public string $texture,
        /**
         * Offset of the texture along the wall: the offset of the seg plus the offset of the sidedef
         */
        public int $textureOffsetX,
        public int $textureOffsetY,
        /**
         * The texture is aligned to the other edge, see {@see \Serafim\Doom\Level\LinedefFlag}
         */
        public bool $isUnpegged,
        /**
         * Light bias of walls that run along an axis: -1 for horizontal ones, +1 for vertical ones
         *
         * @var int<-1, 1>
         */
        public int $lightBias,
        /**
         * The texture scrolls (linedef special 48)
         */
        public bool $isScrolling,
    ) {}

    public function getLength(): float
    {
        return \hypot($this->end->x - $this->start->x, $this->end->y - $this->start->y);
    }
}
