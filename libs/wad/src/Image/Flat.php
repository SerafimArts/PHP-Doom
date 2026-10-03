<?php

declare(strict_types=1);

namespace Local\WAD\Image;

/**
 * A floor or ceiling texture: a 64 pixels wide image of palette indices without transparency.
 */
final readonly class Flat
{
    public function __construct(
        public string $name,
        /** @var int<0, max> */
        public int $width,
        /** @var int<0, max> */
        public int $height,
        /**
         * Palette indices, one byte per pixel in row-major order
         */
        public string $pixels,
    ) {}
}
