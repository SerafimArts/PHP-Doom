<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * An entry of SIDEDEFS: the textures and the sector of one side of a line.
 */
final readonly class Sidedef
{
    public function __construct(
        /** @var int<-32768, 32767> */
        public int $xOffset,
        /** @var int<-32768, 32767> */
        public int $yOffset,
        /**
         * Texture name of the part above the opening, {@see null} for none
         */
        public ?string $upperTexture,
        /**
         * Texture name of the part below the opening, {@see null} for none
         */
        public ?string $lowerTexture,
        /**
         * Texture name of the middle part, {@see null} for none
         */
        public ?string $middleTexture,
        /**
         * Index into SECTORS
         *
         * @var int<0, 65535>
         */
        public int $sector,
    ) {}
}
