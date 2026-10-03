<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An entry of SEGS: a piece of a linedef that belongs to one subsector.
 */
final readonly class Seg
{
    public function __construct(
        /** @var int<0, 65535> */
        public int $startVertex,
        /** @var int<0, 65535> */
        public int $endVertex,
        /**
         * Direction as a binary angle, 65536 is the full circle
         *
         * @var int<0, 65535>
         */
        public int $angle,
        /**
         * Index into LINEDEFS
         *
         * @var int<0, 65535>
         */
        public int $linedef,
        /**
         * Whether the seg runs against the direction of its linedef (the left side faces the subsector)
         */
        public bool $isOpposite,
        /**
         * Distance along the linedef from its start vertex to the start of the seg
         *
         * @var int<-32768, 32767>
         */
        public int $offset,
    ) {}
}
