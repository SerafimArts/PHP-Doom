<?php

declare(strict_types=1);

namespace Serafim\Doom\Render;

/**
 * A viewpoint in the level: the eye and the direction of the view. Doom has no pitch, the angle runs counter-clockwise
 * from the east.
 */
final class Camera
{
    /**
     * Doom draws a 320x200 picture on a 4:3 screen: the pixels are taller than wide.
     */
    public const float ASPECT = 4 / 3;

    /**
     * Tangent of half of the horizontal field of view (90 degrees)
     */
    public const float TANGENT_X = 1.0;

    private const float NEAR = 2.0;

    private const float FAR = 16384.0;

    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $z = 41.0,
        /**
         * In radians
         */
        public float $yaw = 0.0,
    ) {}

    /**
     * The 4x4 matrix (column-major, 64 bytes) that maps the map space (x east, y north, z up) to the clip space of
     * Vulkan: a horizontal field of view of 90 degrees, y down, depth from 0 to 1.
     */
    public function packViewProjection(): string
    {
        $tanX = self::TANGENT_X;
        $tanY = $tanX / self::ASPECT;

        $sin = \sin($this->yaw);
        $cos = \cos($this->yaw);

        $rightX = $sin;
        $rightY = -$cos;
        $forwardX = $cos;
        $forwardY = $sin;

        $rightDot = $rightX * $this->x + $rightY * $this->y;
        $forwardDot = $forwardX * $this->x + $forwardY * $this->y;

        $a = self::FAR / (self::FAR - self::NEAR);
        $b = self::FAR * self::NEAR / (self::FAR - self::NEAR);

        $rows = [
            [$rightX / $tanX, $rightY / $tanX, 0.0, -$rightDot / $tanX],
            [0.0, 0.0, -1.0 / $tanY, $this->z / $tanY],
            [$forwardX * $a, $forwardY * $a, 0.0, -$forwardDot * $a - $b],
            [$forwardX, $forwardY, 0.0, -$forwardDot],
        ];

        $data = '';

        for ($column = 0; $column < 4; ++$column) {
            for ($row = 0; $row < 4; ++$row) {
                $data .= \pack('f', $rows[$row][$column]);
            }
        }

        return $data;
    }

    /**
     * The uniform block of a frame (112 bytes): the matrix, the eye with the time, the palette with the size of the
     * canvas, and the direction of the view.
     *
     * @param int<0, max> $palette
     * @param int<0, max> $skyPicture
     */
    public function packFrame(
        float $time,
        int $palette,
        int $width,
        int $height,
        int $skyPicture,
        int $extraLight = 0,
        bool $isRayLight = false,
    ): string {
        return $this->packViewProjection()
            . \pack('f4', $this->x, $this->y, $this->z, $time)
            . \pack('V4', $palette, $width, $height, $skyPicture)
            . \pack('f4', $this->yaw, self::TANGENT_X, (float) $extraLight, (float) $isRayLight);
    }
}
