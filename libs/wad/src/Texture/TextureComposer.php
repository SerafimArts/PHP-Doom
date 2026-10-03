<?php

declare(strict_types=1);

namespace Local\WAD\Texture;

use Local\WAD\Image\Picture;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\WAD
 */
final readonly class TextureComposer
{
    private function __construct() {}

    /**
     * @param \Closure(string): Picture $loadPatch returns the patch by its lump name
     */
    public static function compose(TextureDefinition $definition, \Closure $loadPatch): Picture
    {
        $width = $definition->width;
        $height = $definition->height;

        $pixels = \str_repeat("\0", $width * $height);
        $mask = $pixels;

        foreach ($definition->patches as $texturePatch) {
            $patch = $loadPatch($texturePatch->patchName);

            $fromX = \max(0, -$texturePatch->originX);
            $toX = \min($patch->width, $width - $texturePatch->originX);
            $fromY = \max(0, -$texturePatch->originY);
            $toY = \min($patch->height, $height - $texturePatch->originY);

            for ($py = $fromY; $py < $toY; ++$py) {
                $source = $py * $patch->width;
                $target = ($py + $texturePatch->originY) * $width + $texturePatch->originX;

                for ($px = $fromX; $px < $toX; ++$px) {
                    if ($patch->mask[$source + $px] === "\0") {
                        continue;
                    }

                    $pixels[$target + $px] = $patch->pixels[$source + $px];
                    $mask[$target + $px] = "\x01";
                }
            }
        }

        return new Picture(
            name: $definition->name,
            width: $width,
            height: $height,
            leftOffset: 0,
            topOffset: 0,
            pixels: $pixels,
            mask: $mask,
        );
    }
}
