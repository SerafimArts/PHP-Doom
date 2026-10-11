<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Support;

use Local\WAD\Wad;
use Serafim\Doom\Render\Atlas\AtlasBuilder;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Data\RenderData;
use Serafim\Doom\Render\Hud\GraphicsSet;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * The sprites, the graphics and the atlas of them that the screen of the player is laid out from.
 */
final readonly class HudFixture
{
    public function __construct(
        public SpriteSet $sprites,
        public GraphicsSet $graphics,
        public TextureAtlas $atlas,
        public RenderData $data,
    ) {}

    /**
     * @return self|null {@see null} when the shareware WAD is missing
     */
    public static function create(RenderData $data): ?self
    {
        $pathname = \dirname(__DIR__, 3) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            return null;
        }

        $wad = Wad::open($pathname);
        $sprites = SpriteSet::create($wad, $data->sprites->frames);
        $graphics = GraphicsSet::create($wad, $data->graphics);

        $builder = new AtlasBuilder();

        foreach ($sprites->frames as $views) {
            foreach ($views as $view) {
                $picture = $view->picture;
                $builder->add(
                    LevelAtlasFactory::getSpriteKey($picture->name),
                    $picture->width,
                    $picture->height,
                    $picture->pixels,
                );
            }
        }

        foreach ($graphics->getAll() as $name => $picture) {
            $builder->add(LevelAtlasFactory::getGraphicKey($name), $picture->width, $picture->height, $picture->pixels);
        }

        return new self($sprites, $graphics, $builder->build(), $data);
    }
}
