<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Layout;

/**
 * The two indirect draws of a frame: the sprites and the hud quads, six vertices each, one instance per picture. Every
 * draw is a `VkDrawIndirectCommand` of 16 bytes.
 */
final class DrawCommand
{
    /**
     * Bytes of one `VkDrawIndirectCommand`
     */
    public const int SIZE = 16;

    /**
     * Where the draw of the quads starts in the buffer
     */
    public const int QUADS_OFFSET = self::SIZE;

    /**
     * Bytes of the buffer of a frame
     */
    public const int BUFFER_SIZE = self::SIZE * 2;

    private const int VERTICES_PER_PICTURE = 6;

    private function __construct() {}

    /**
     * @param int<0, max> $spriteCount
     * @param int<0, max> $quadCount
     */
    public static function pack(int $spriteCount, int $quadCount): string
    {
        return \pack(
            'V8',
            self::VERTICES_PER_PICTURE,
            $spriteCount,
            0,
            0,
            self::VERTICES_PER_PICTURE,
            $quadCount,
            0,
            0,
        );
    }
}
