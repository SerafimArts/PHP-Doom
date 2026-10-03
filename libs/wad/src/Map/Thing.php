<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An entry of THINGS: a monster, an item, a decoration or a player start.
 */
final readonly class Thing
{
    public function __construct(
        /** @var int<-32768, 32767> */
        public int $x,
        /** @var int<-32768, 32767> */
        public int $y,
        /**
         * Facing in degrees, 0 is east and the angle grows counterclockwise
         *
         * @var int<0, 65535>
         */
        public int $angle,
        /**
         * The editor number of the object type
         *
         * @var int<0, 65535>
         */
        public int $type,
        /**
         * Skill and ambush bits
         *
         * @var int<0, 65535>
         */
        public int $flags,
    ) {}
}
