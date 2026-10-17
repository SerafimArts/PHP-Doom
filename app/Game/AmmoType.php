<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * The kinds of ammunition that the weapons of the first level use.
 */
enum AmmoType: string
{
    case BULLETS = 'bullets';

    case SHELLS = 'shells';

    /**
     * How much of the ammunition the player can carry
     *
     * @return int<1, max>
     */
    public function getCapacity(): int
    {
        return match ($this) {
            self::BULLETS => 200,
            self::SHELLS => 50,
        };
    }
}
