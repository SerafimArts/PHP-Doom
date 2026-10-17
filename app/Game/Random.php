<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * The random numbers of the game. The sequence depends on the seed only, so a game can be repeated.
 */
final class Random
{
    private int $state;

    public function __construct(int $seed = 1)
    {
        $this->state = $seed & 0xFFFFFFFF;

        if ($this->state === 0) {
            $this->state = 1;
        }
    }

    /**
     * A number from 0 to 255.
     *
     * @return int<0, 255>
     */
    public function nextByte(): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x;

        return $x >> 8 & 0xFF;
    }
}
