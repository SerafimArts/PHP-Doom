<?php

declare(strict_types=1);

namespace Local\GPU\Window;

/**
 * Events that GLFW callbacks collect while the window polls them.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\GPU
 */
final class WindowState
{
    /** @var list<KeyEvent> */
    public array $keyEvents = [];

    public bool $isResized = false;

    /** @var list<string> */
    public array $errors = [];
}
