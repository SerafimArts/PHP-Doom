<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Session;

use Local\GPU\Window\Window;

/**
 * Counts the frames and writes their number per second and the state of the game into the title of the window.
 */
final class FpsTitle
{
    private int $frames = 0;

    private float $since;

    public function __construct(
        private readonly Window $window,
        private readonly string $name = 'Doom',
    ) {
        $this->since = $window->getTime();
    }

    /**
     * Counts a frame drawn at the time.
     *
     * @param \Closure(): string $describe what the title says besides the frames, it runs once per second
     */
    public function tick(float $now, \Closure $describe): void
    {
        ++$this->frames;

        if ($now - $this->since < 1.0) {
            return;
        }

        $this->window->setTitle(\sprintf('%s - %d fps - %s', $this->name, $this->frames, $describe()));
        $this->frames = 0;
        $this->since = $now;
    }
}
