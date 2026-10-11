<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Layout;

use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * The struct `SpriteView` of `sprite.vert` for every view of every frame of the sprites: the picture, the offsets, the
 * size and the mirror.
 */
final class SpriteViewTable
{
    /**
     * Bytes of one view
     */
    public const int SIZE = 32;

    private function __construct() {}

    public static function pack(TextureAtlas $atlas, SpriteSet $sprites): string
    {
        $data = '';

        foreach ($sprites->frames as $views) {
            foreach ($views as $view) {
                $picture = $view->picture;

                $data .= \pack(
                    'VllVVVVV',
                    $atlas->getNumber(LevelAtlasFactory::getSpriteKey($picture->name)),
                    $picture->leftOffset,
                    $picture->topOffset,
                    $picture->width,
                    $picture->height,
                    (int) $view->isFlipped,
                    0,
                    0,
                );
            }
        }

        if ($data === '') {
            return \str_repeat("\0", self::SIZE);
        }

        return $data;
    }
}
