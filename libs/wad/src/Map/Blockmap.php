<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * The grid of 128 x 128 blocks with the lines that touch every block.
 */
final readonly class Blockmap
{
    public const int BLOCK_SIZE = 128;

    public function __construct(
        /**
         * X of the south west corner of the grid
         *
         * @var int<-32768, 32767>
         */
        public int $originX,
        /**
         * Y of the south west corner of the grid
         *
         * @var int<-32768, 32767>
         */
        public int $originY,
        /** @var int<0, 65535> */
        public int $columns,
        /** @var int<0, 65535> */
        public int $rows,
        /**
         * Indices into LINEDEFS for every block. Blocks go east first and then north:
         * the block of a column and a row is at the index `row * columns + column`.
         *
         * @var list<list<int<0, 65534>>>
         */
        public array $blocks,
    ) {}
}
