<?php

declare(strict_types=1);

namespace Serafim\Doom\Render;

use Serafim\Doom\Render\Hud\HudBuilder;
use Serafim\Doom\Render\Hud\IntermissionBuilder;
use Serafim\Doom\Render\Hud\IntermissionStats;
use Serafim\Doom\Render\Layout\SpriteInstance;
use Serafim\Doom\Render\Raytracing\CasterBuilder;
use Serafim\Doom\Render\Raytracing\LightBuilder;
use Serafim\Doom\Render\Scene\SceneState;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * Collects what a frame shows on top of the level: the sprites of the things and the screen of the player.
 */
final class SceneBuilder
{
    public function __construct(
        private readonly SpriteSet $sprites,
        private readonly HudBuilder $hud,
        private readonly LightBuilder $lights,
        private readonly CasterBuilder $casters,
        private readonly ?IntermissionBuilder $intermission = null,
    ) {}

    /**
     * The screen after the exit of a level: the pictures of the intermission cover the level.
     */
    public function buildIntermission(IntermissionStats $result): Scene
    {
        $quads = '';
        $quadCount = 0;

        foreach ($this->intermission?->build($result) ?? [] as $quad) {
            if ($quadCount >= Scene::MAX_QUADS) {
                break;
            }

            $quads .= $quad->pack();
            ++$quadCount;
        }

        return new Scene('', 0, $quads, \min(Scene::MAX_QUADS, $quadCount));
    }

    /**
     * @param float $time seconds, the lamps flicker with it
     * @param bool $withHud the status bar and the weapon of the player
     */
    public function build(SceneState $state, float $time = 0.0, bool $withHud = true): Scene
    {
        $sprites = '';
        $spriteCount = 0;

        foreach ($state->things as $thing) {
            if ($spriteCount >= Scene::MAX_SPRITES) {
                continue;
            }

            $frame = $this->sprites->findFrame($thing->sprite, $thing->frame);

            if ($frame === null) {
                continue;
            }

            $sprites .= SpriteInstance::pack(
                $thing->x,
                $thing->y,
                $thing->z,
                $thing->angle,
                $frame,
                $thing->light,
                $thing->isFullBright,
            );
            ++$spriteCount;
        }

        $quads = '';
        $quadCount = 0;

        $hudQuads = [];

        if ($withHud) {
            $hudQuads = $this->hud->build($state->hud);
        }

        foreach ($hudQuads as $quad) {
            if ($quadCount >= Scene::MAX_QUADS) {
                break;
            }

            $quads .= $quad->pack();
            ++$quadCount;
        }

        [$lights] = $this->lights->build($state, $time);
        [$casters] = $this->casters->build($state);

        return new Scene(
            $sprites,
            \min(Scene::MAX_SPRITES, $spriteCount),
            $quads,
            \min(Scene::MAX_QUADS, $quadCount),
            $state->extraLight,
            $lights,
            $casters,
        );
    }
}
