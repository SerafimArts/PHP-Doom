<?php

declare(strict_types=1);

namespace Local\GLFW\Constants;

/**
 * Constants of GLFW 3.4 (the FFI header of `local/glfw` has no `#define`).
 */
final class Glfw
{
    public const int FALSE = 0;

    public const int TRUE = 1;

    /**
     * The GLFW value of a boolean: {@see self::TRUE} or {@see self::FALSE}.
     */
    public static function fromBool(bool $value): int
    {
        if ($value) {
            return self::TRUE;
        }

        return self::FALSE;
    }

    public const int RELEASE = 0;

    public const int PRESS = 1;

    public const int REPEAT = 2;

    public const int RESIZABLE = 0x0002_0003;

    public const int VISIBLE = 0x0002_0004;

    public const int DECORATED = 0x0002_0005;

    public const int CLIENT_API = 0x0002_2001;

    public const int NO_API = 0;

    public const int CURSOR = 0x0003_3001;

    public const int RAW_MOUSE_MOTION = 0x0003_3005;

    public const int CURSOR_NORMAL = 0x0003_4001;

    public const int CURSOR_DISABLED = 0x0003_4003;

    private function __construct() {}
}
