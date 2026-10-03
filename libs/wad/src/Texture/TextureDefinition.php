<?php

declare(strict_types=1);

namespace Local\WAD\Texture;

/**
 * An entry of TEXTURE1 or TEXTURE2: the size of a wall texture and the patches it is made of.
 */
final readonly class TextureDefinition
{
    public function __construct(
        /**
         * Name in upper case
         */
        public string $name,
        /** @var int<0, 65535> */
        public int $width,
        /** @var int<0, 65535> */
        public int $height,
        /**
         * Patches in the order they are drawn, the next patch covers the previous ones
         *
         * @var list<TexturePatch>
         */
        public array $patches,
    ) {}
}
