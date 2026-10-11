<?php

declare(strict_types=1);

namespace Serafim\Doom\Render;

/**
 * What a frame shows on top of the walls and the floors, packed as the shaders read it: the sprites of the things (32
 * bytes each) and the pictures of the screen (32 bytes each).
 */
final readonly class Scene
{
    public const int MAX_SPRITES = 512;

    public const int MAX_QUADS = 256;

    public function __construct(
        public string $sprites = '',
        /** @var int<0, 512> */
        public int $spriteCount = 0,
        public string $quads = '',
        /** @var int<0, 256> */
        public int $quadCount = 0,
        /**
         * Light that the flash of a shot adds to the walls, the floors and the sprites, in steps of the colormap
         *
         * @var int<0, 2>
         */
        public int $extraLight = 0,
        /**
         * The lights that shine on the level, 32 bytes each (`ShadowScene::LIGHT_SIZE`)
         */
        public string $lights = '',
        /**
         * The boxes of the solid things that put shadows, 20 bytes each (`ShadowScene::CASTER_SIZE`)
         */
        public string $casters = '',
    ) {}
}
