<?php

declare(strict_types=1);

namespace Local\WAD\Texture;

final readonly class TexturePatch
{
    public function __construct(
        /**
         * Name of the patch lump in upper case
         */
        public string $patchName,
        /**
         * Horizontal position of the patch top left corner on the texture
         */
        public int $originX,
        /**
         * Vertical position of the patch top left corner on the texture
         */
        public int $originY,
    ) {}
}
