<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

/**
 * Bits of the `flags` field of a linedef.
 */
final class LinedefFlag
{
    /**
     * Blocks the movement of players and monsters
     */
    public const int BLOCKING = 0x0001;

    /**
     * Blocks the movement of monsters only
     */
    public const int BLOCK_MONSTERS = 0x0002;

    public const int TWO_SIDED = 0x0004;

    /**
     * The upper texture is aligned to the ceiling of the front sector, not to the lower edge
     */
    public const int DONT_PEG_TOP = 0x0008;

    /**
     * The lower and the middle textures are aligned to the ceiling, not to the floor
     */
    public const int DONT_PEG_BOTTOM = 0x0010;

    /**
     * Shown as a one-sided wall on the automap
     */
    public const int SECRET = 0x0020;

    public const int BLOCK_SOUND = 0x0040;

    /**
     * Never shown on the automap
     */
    public const int DONT_DRAW = 0x0080;

    /**
     * Shown on the automap from the start
     */
    public const int MAPPED = 0x0100;

    private function __construct() {}
}
