<?php

declare(strict_types=1);

namespace Local\GPU\Window;

use Local\GLFW\Constants\Glfw;

/**
 * A key that was pressed, released or repeated.
 */
final readonly class KeyEvent
{
    public function __construct(
        /**
         * Key code of GLFW, {@see Key}
         */
        public int $key,
        /**
         * Action of GLFW: {@see Glfw::PRESS}, {@see Glfw::RELEASE} or {@see Glfw::REPEAT}
         */
        public int $action,
    ) {}

    public function isPress(): bool
    {
        return $this->action === Glfw::PRESS;
    }
}
