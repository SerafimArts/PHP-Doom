<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An entry of SSECTORS: a convex area that is made of a run of consecutive segs.
 */
final readonly class Subsector
{
    public function __construct(
        /** @var int<0, 65535> */
        public int $segCount,
        /**
         * Index into SEGS of the first seg
         *
         * @var int<0, 65535>
         */
        public int $firstSeg,
    ) {}
}
