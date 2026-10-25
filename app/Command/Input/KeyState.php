<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Input;

/**
 * Which keys and mouse buttons are held down right now.
 */
interface KeyState
{
    public function isKeyDown(int $key): bool;

    public function isMouseButtonDown(int $button): bool;
}
