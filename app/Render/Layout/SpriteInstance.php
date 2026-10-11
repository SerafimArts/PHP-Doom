<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Layout;

/**
 * The struct `SpriteInstance` of `sprite.vert`: where a thing stands and which frame it shows.
 */
final class SpriteInstance
{
    /**
     * Bytes of one instance
     */
    public const int SIZE = 32;

    private function __construct() {}

    /**
     * @param int<0, max> $frame number of the frame in the table of the sprite views
     */
    public static function pack(
        float $x,
        float $y,
        float $z,
        float $angle,
        int $frame,
        int $light,
        bool $isFullBright,
    ): string {
        return \pack('f4V4', $x, $y, $z, $angle, $frame, $light, (int) $isFullBright, 0);
    }
}
