<?php

declare(strict_types=1);

namespace Local\GLFW\Constants;

final class MouseButton
{
    public const int LEFT = 0;

    public const int RIGHT = 1;

    public const int MIDDLE = 2;

    /**
     * The code of the constant that has the name, {@see null} for a name that no constant has.
     */
    public static function findCode(string $name): ?int
    {
        $constant = self::class . '::' . $name;

        if (!\defined($constant)) {
            return null;
        }

        $code = \constant($constant);
        \assert(\is_int($code));

        return $code;
    }

    private function __construct() {}
}
