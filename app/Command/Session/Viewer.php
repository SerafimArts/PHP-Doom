<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Session;

use Local\GPU\Device;
use Local\GPU\Window\Window;
use Local\WAD\Wad;
use Serafim\Doom\Game\GameData;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Data\RenderData;
use Serafim\Doom\Render\Hud\GraphicsSet;
use Serafim\Doom\Render\LevelRenderer;
use Serafim\Doom\Render\SceneBuilder;
use Serafim\Doom\Render\SceneBuilderFactory;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * Everything a command needs to show a level: the WAD and the level in it, the tables of the game, the device, the
 * window and the renderer.
 */
final readonly class Viewer
{
    public function __construct(
        public Wad $wad,
        public Level $level,
        public GameData $data,
        public RenderData $render,
        public Device $device,
        public SpriteSet $sprites,
        public GraphicsSet $graphics,
        public TextureAtlas $atlas,
        public LevelRenderer $renderer,
        /**
         * {@see null} for a command that draws without a window
         */
        public ?Window $window = null,
    ) {}

    /**
     * @param bool $hasIntermission the builder can draw the screen after the exit of a level
     */
    public function createSceneBuilder(bool $hasIntermission = false): SceneBuilder
    {
        return new SceneBuilderFactory($this->render, $this->graphics, $this->atlas, $this->sprites)
            ->create($hasIntermission);
    }
}
