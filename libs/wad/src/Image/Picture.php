<?php

declare(strict_types=1);

namespace Local\WAD\Image;

/**
 * An image of palette indices with a transparency mask: a patch (wall piece, sprite, interface graphic)
 * or a composite wall texture.
 */
final readonly class Picture
{
    public bool $isSolid;

    public function __construct(
        public string $name,
        /** @var int<0, max> */
        public int $width,
        /** @var int<0, max> */
        public int $height,
        /**
         * Horizontal distance from the left edge to the origin point (the sprite hotspot)
         */
        public int $leftOffset,
        /**
         * Vertical distance from the top edge to the origin point (the sprite hotspot)
         */
        public int $topOffset,
        /**
         * Palette indices, one byte per pixel in row-major order. A transparent pixel holds 0.
         */
        public string $pixels,
        /**
         * One byte per pixel in row-major order: "\x00" for a transparent pixel, "\x01" for an opaque one
         */
        public string $mask,
    ) {
        $this->isSolid = !\str_contains($mask, "\0");
    }
}
