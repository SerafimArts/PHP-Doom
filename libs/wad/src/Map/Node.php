<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An entry of NODES: a splitting line of the BSP tree with the two halves of the space.
 */
final readonly class Node
{
    public function __construct(
        /**
         * X of the start of the splitting line
         *
         * @var int<-32768, 32767>
         */
        public int $x,
        /**
         * Y of the start of the splitting line
         *
         * @var int<-32768, 32767>
         */
        public int $y,
        /**
         * X length of the splitting line vector
         *
         * @var int<-32768, 32767>
         */
        public int $dx,
        /**
         * Y length of the splitting line vector
         *
         * @var int<-32768, 32767>
         */
        public int $dy,
        /**
         * The box around everything on the right side of the line
         */
        public BoundingBox $rightBox,
        /**
         * The box around everything on the left side of the line
         */
        public BoundingBox $leftBox,
        public NodeChild $rightChild,
        public NodeChild $leftChild,
    ) {}
}
