<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Mesh;

/**
 * The static geometry of a level in the layout the shaders read.
 *
 * A wall part is 64 bytes (`WALL_SIZE`): the two ends `float x1, y1, x2, y2`, then `uint frontSector, backSector
 * (`NO_SECTOR` for none), type (0 upper, 1 lower, 2 middle), picture`, then `float textureOffsetX, textureOffsetY,
 * span`, `uint flags` (bit 0 unpegged, bit 1 scrolling, bit 2 masked), `int lightBias`, and three unused words.
 * A flat vertex is 12 bytes (`FLAT_VERTEX_SIZE`): `float x, y` and `uint sector`.
 */
final readonly class LevelMesh
{
    public const int WALL_SIZE = 64;

    public const int FLAT_VERTEX_SIZE = 12;

    public const int NO_SECTOR = 0xFFFFFFFF;

    public const int WALL_UNPEGGED = 1;

    public const int WALL_SCROLLING = 2;

    public const int WALL_MASKED = 4;

    public function __construct(
        public string $walls,
        /** @var int<0, 4294967295> */
        public int $wallCount,
        public string $flatVertices,
        /** @var int<0, 4294967295> */
        public int $flatVertexCount,
    ) {}
}
