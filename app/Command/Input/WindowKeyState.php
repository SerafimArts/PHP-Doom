<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Input;

use Local\GPU\Window\Window;

/**
 * The keys of a window.
 */
final readonly class WindowKeyState implements KeyState
{
    public function __construct(
        private Window $window,
    ) {}

    #[\Override]
    public function isKeyDown(int $key): bool
    {
        return $this->window->isKeyDown($key);
    }

    #[\Override]
    public function isMouseButtonDown(int $button): bool
    {
        return $this->window->isMouseButtonDown($button);
    }
}
