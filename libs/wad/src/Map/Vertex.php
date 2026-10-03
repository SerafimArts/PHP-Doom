<?php

declare(strict_types=1);

namespace Local\WAD\Map;

final readonly class Vertex
{
    public function __construct(
        /** @var int<-32768, 32767> */
        public int $x,
        /** @var int<-32768, 32767> */
        public int $y,
    ) {}
}
