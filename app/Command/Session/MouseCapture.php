<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Session;

use Local\GPU\Window\Window;

/**
 * Keeps the cursor inside the window and tells how far the mouse moved to the side since it was asked last.
 */
final class MouseCapture
{
    private bool $isCaptured = true;

    private float $lastX;

    public function __construct(
        private readonly Window $window,
    ) {
        $window->setCursorCaptured(true);
        [$this->lastX] = $window->getCursorPosition();
    }

    /**
     * Captures the cursor or lets it go.
     */
    public function toggle(): void
    {
        $this->isCaptured = !$this->isCaptured;
        $this->window->setCursorCaptured($this->isCaptured);
        [$this->lastX] = $this->window->getCursorPosition();
    }

    /**
     * The motion in pixels since the last call, to the right is positive, zero while the cursor is free.
     */
    public function readMotion(): float
    {
        if (!$this->isCaptured) {
            return 0.0;
        }

        [$x] = $this->window->getCursorPosition();
        $motion = $x - $this->lastX;
        $this->lastX = $x;

        return $motion;
    }
}
