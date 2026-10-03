<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An entry of LINEDEFS: a wall line between two vertices with one or two sides.
 */
final readonly class Linedef
{
    public function __construct(
        /**
         * Index into VERTEXES
         *
         * @var int<0, 65535>
         */
        public int $startVertex,
        /**
         * Index into VERTEXES
         *
         * @var int<0, 65535>
         */
        public int $endVertex,
        /**
         * Bits: blocking, two-sided, unpegged textures and so on
         *
         * @var int<0, 65535>
         */
        public int $flags,
        /**
         * The action type of the line, 0 for none
         *
         * @var int<0, 65535>
         */
        public int $special,
        /**
         * The number of the sectors the action works on
         *
         * @var int<0, 65535>
         */
        public int $tag,
        /**
         * Index into SIDEDEFS of the side on the right of the line direction
         *
         * @var int<0, 65535>
         */
        public int $rightSidedef,
        /**
         * Index into SIDEDEFS of the side on the left, {@see null} for a one-sided line
         *
         * @var int<0, 65534>|null
         */
        public ?int $leftSidedef,
    ) {}
}
