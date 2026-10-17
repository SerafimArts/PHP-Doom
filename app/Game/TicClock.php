<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * Turns the real time into game tics: the game runs 35 tics per second whatever the frame rate is.
 */
final class TicClock
{
    public const int RATE = 35;

    public const int MAX_TICS_PER_FRAME = 5;

    private ?float $previous = null;

    private float $accumulator = 0.0;

    /**
     * How many tics to run for a frame that starts at the given time (seconds). The delay of a slow frame is dropped
     * when it needs more than the maximum of tics.
     *
     * @return int<0, 5>
     */
    public function advance(float $now): int
    {
        $this->accumulator += $now - ($this->previous ?? $now);
        $this->previous = $now;

        $duration = 1 / self::RATE;
        $tics = \min(self::MAX_TICS_PER_FRAME, (int) \floor($this->accumulator / $duration));

        $this->accumulator -= $tics * $duration;

        if ($this->accumulator >= $duration) {
            $this->accumulator = 0.0;
        }

        return \max(0, $tics);
    }

    /**
     * How far the time is from the last tic to the next one, from 0 to 1.
     */
    public function getAlpha(): float
    {
        return \min(1.0, $this->accumulator * self::RATE);
    }
}
