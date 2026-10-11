<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Atlas;

/**
 * Where a picture lives in the atlas, in texels.
 */
final readonly class AtlasRect
{
    public function __construct(
        /** @var int<0, max> */
        public int $x,
        /** @var int<0, max> */
        public int $y,
        /** @var int<1, max> */
        public int $width,
        /** @var int<1, max> */
        public int $height,
    ) {}
}
