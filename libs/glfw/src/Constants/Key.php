<?php

declare(strict_types=1);

namespace Local\GLFW\Constants;

/**
 * Key codes of GLFW (US layout, letters and digits match ASCII).
 */
final class Key
{
    public const int SPACE = 32;

    public const int NUM_0 = 48;

    public const int NUM_1 = 49;

    public const int NUM_2 = 50;

    public const int NUM_3 = 51;

    public const int NUM_4 = 52;

    public const int NUM_5 = 53;

    public const int NUM_6 = 54;

    public const int NUM_7 = 55;

    public const int NUM_8 = 56;

    public const int NUM_9 = 57;

    public const int A = 65;

    public const int D = 68;

    public const int E = 69;

    public const int Q = 81;

    public const int S = 83;

    public const int W = 87;

    public const int L = 76;

    public const int ESCAPE = 256;

    public const int ENTER = 257;

    public const int TAB = 258;

    public const int RIGHT = 262;

    public const int LEFT = 263;

    public const int DOWN = 264;

    public const int UP = 265;

    public const int F2 = 291;

    public const int LEFT_SHIFT = 340;

    public const int LEFT_CONTROL = 341;

    public const int LEFT_ALT = 342;

    public const int RIGHT_SHIFT = 344;

    public const int RIGHT_CONTROL = 345;

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
