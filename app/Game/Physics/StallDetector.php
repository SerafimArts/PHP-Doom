<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Physics;

/**
 * Notices a player that holds a move command and does not move. One stall is reported once, at most a few stalls are
 * reported.
 */
final class StallDetector
{
    /**
     * Tics of a stall before it is reported: a second
     */
    public const int STALL_TICS = 35;

    public const int MAX_REPORTS = 20;

    /**
     * Distance below which the player has not moved in a tic
     */
    private const float STILL_DISTANCE = 0.05;

    private int $stalledTics = 0;

    private int $reports = 0;

    private ?float $lastX = null;

    private ?float $lastY = null;

    /**
     * Takes the position after a tic and tells whether the stall that it belongs to has to be reported now.
     */
    public function update(float $x, float $y, bool $isMoveHeld): bool
    {
        $isStill = $this->lastX !== null
            && $this->lastY !== null
            && \hypot($x - $this->lastX, $y - $this->lastY) < self::STILL_DISTANCE;

        $this->lastX = $x;
        $this->lastY = $y;

        if (!$isMoveHeld || !$isStill) {
            $this->stalledTics = 0;

            return false;
        }

        ++$this->stalledTics;

        if ($this->stalledTics !== self::STALL_TICS || $this->reports >= self::MAX_REPORTS) {
            return false;
        }

        ++$this->reports;

        return true;
    }

    public function getReportCount(): int
    {
        return $this->reports;
    }
}
