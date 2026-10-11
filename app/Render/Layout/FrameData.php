<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Layout;

/**
 * The uniform block `Frame` of `level.glsl`: the view projection matrix, the eye, the palette and the size of the
 * canvas.
 */
final class FrameData
{
    /**
     * Bytes of the block
     */
    public const int SIZE = 112;

    private function __construct() {}
}
