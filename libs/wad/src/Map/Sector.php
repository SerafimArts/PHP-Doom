<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An entry of SECTORS: an area with a floor, a ceiling and a light level.
 */
final readonly class Sector
{
    public function __construct(
        /** @var int<-32768, 32767> */
        public int $floorHeight,
        /** @var int<-32768, 32767> */
        public int $ceilingHeight,
        public string $floorTexture,
        /**
         * Flat name of the ceiling, F_SKY1 marks the sky
         */
        public string $ceilingTexture,
        /** @var int<0, 65535> */
        public int $lightLevel,
        /**
         * The effect type of the sector (damage, blinking light, secret), 0 for none
         *
         * @var int<0, 65535>
         */
        public int $special,
        /**
         * The number that lines refer to
         *
         * @var int<0, 65535>
         */
        public int $tag,
    ) {}
}
