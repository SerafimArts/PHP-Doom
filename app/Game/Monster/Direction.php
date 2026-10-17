<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

/**
 * The direction a monster walks in: `dirtype_t` of linuxdoom, counter-clockwise from the east by 45 degrees.
 */
enum Direction: int
{
    case EAST = 0;
    case NORTH_EAST = 1;
    case NORTH = 2;
    case NORTH_WEST = 3;
    case WEST = 4;
    case SOUTH_WEST = 5;
    case SOUTH = 6;
    case SOUTH_EAST = 7;
    case NONE = 8;

    /**
     * The eight directions to walk in, in the order of the circle.
     *
     * @return list<self>
     */
    public static function getCompass(): array
    {
        return [
            self::EAST,
            self::NORTH_EAST,
            self::NORTH,
            self::NORTH_WEST,
            self::WEST,
            self::SOUTH_WEST,
            self::SOUTH,
            self::SOUTH_EAST,
        ];
    }

    /**
     * The direction of a circle octant, the angle in eighths of a circle counted from the east.
     */
    public static function createFromOctant(int $octant): self
    {
        return self::getCompass()[$octant & 7];
    }

    /**
     * The direction half a circle away, {@see self::NONE} stays as it is.
     */
    public function getOpposite(): self
    {
        if ($this === self::NONE) {
            return self::NONE;
        }

        return self::createFromOctant($this->value + 4);
    }

    /**
     * The angle of the direction in radians, counter-clockwise from the east.
     */
    public function getAngle(): float
    {
        return $this->value * \M_PI / 4;
    }
}
