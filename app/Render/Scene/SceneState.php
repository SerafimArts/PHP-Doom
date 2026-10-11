<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Scene;

use Serafim\Doom\Render\Hud\HudState;

/**
 * What a frame needs of the game: the things that stand in the level, the view of the player and the screen of the
 * player. The game produces it once per frame, the renderer reads nothing else of the game.
 */
final readonly class SceneState
{
    public function __construct(
        /**
         * The things that are not removed
         *
         * @var list<SceneThing>
         */
        public array $things,
        /**
         * Where the eyes of the player are at the last tic
         */
        public float $eyeX,
        public float $eyeY,
        public float $eyeZ,
        /**
         * Counter-clockwise from the east, in radians
         */
        public float $yaw,
        /**
         * Where the feet of the player are
         */
        public float $playerX,
        public float $playerY,
        /**
         * Light that a muzzle flash adds to the whole scene, in steps of the colormap
         *
         * @var int<0, 2>
         */
        public int $extraLight,
        /**
         * The weapon in the hands shows a flash
         */
        public bool $isFlashing,
        public HudState $hud,
    ) {}
}
