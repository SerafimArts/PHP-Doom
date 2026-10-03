<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An axis aligned box in map coordinates.
 */
final readonly class BoundingBox
{
    public function __construct(
        /** @var int<-32768, 32767> */
        public int $top,
        /** @var int<-32768, 32767> */
        public int $bottom,
        /** @var int<-32768, 32767> */
        public int $left,
        /** @var int<-32768, 32767> */
        public int $right,
    ) {}
}
