<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Sprite;

use Local\WAD\Image\Picture;

/**
 * The picture that shows a sprite frame from one side. The mirrored side shares the picture of the opposite side.
 */
final readonly class SpriteRotation
{
    public function __construct(
        public Picture $picture,
        public bool $isFlipped,
    ) {}
}
