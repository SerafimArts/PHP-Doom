<?php

declare(strict_types=1);

namespace Serafim\Doom\Render;

use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Data\RenderData;
use Serafim\Doom\Render\Hud\GraphicsSet;
use Serafim\Doom\Render\Hud\HudBuilder;
use Serafim\Doom\Render\Hud\IntermissionBuilder;
use Serafim\Doom\Render\Raytracing\CasterBuilder;
use Serafim\Doom\Render\Raytracing\LightBuilder;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * Puts a {@see SceneBuilder} together from the tables of the renderer and the pictures of the WAD.
 */
final readonly class SceneBuilderFactory
{
    public function __construct(
        private RenderData $data,
        private GraphicsSet $graphics,
        private TextureAtlas $atlas,
        private SpriteSet $sprites,
    ) {}

    /**
     * @param bool $hasIntermission the builder can draw the screen after the exit of a level
     */
    public function create(bool $hasIntermission = false): SceneBuilder
    {
        $hud = new HudBuilder(
            $this->graphics,
            $this->atlas,
            $this->sprites,
            $this->data->statusBar,
            $this->data->face,
            $this->data->limits,
        );

        $intermission = null;

        if ($hasIntermission) {
            $intermission = new IntermissionBuilder($this->graphics, $this->atlas, $this->data->intermission);
        }

        return new SceneBuilder(
            $this->sprites,
            $hud,
            new LightBuilder($this->data->lights, $this->data->limits),
            new CasterBuilder($this->data->lights->lampThing),
            $intermission,
        );
    }
}
