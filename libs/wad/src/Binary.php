<?php

declare(strict_types=1);

namespace Local\WAD;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\WAD
 */
final readonly class Binary
{
    private function __construct() {}

    /**
     * @return int<0, 65535>
     */
    public static function uint16(string $data, int $offset): int
    {
        /** @var int<0, 65535> */
        return \ord($data[$offset]) | \ord($data[$offset + 1]) << 8;
    }

    /**
     * @return int<-32768, 32767>
     */
    public static function int16(string $data, int $offset): int
    {
        $value = self::uint16($data, $offset);

        if ($value >= 0x8000) {
            return $value - 0x10000;
        }

        return $value;
    }

    /**
     * @return int<0, 4294967295>
     */
    public static function uint32(string $data, int $offset): int
    {
        /** @var int<0, 4294967295> */
        return \ord($data[$offset])
            | \ord($data[$offset + 1]) << 8
            | \ord($data[$offset + 2]) << 16
            | \ord($data[$offset + 3]) << 24;
    }

    /**
     * Reads the name of 8 bytes padded with NUL and returns it in upper case.
     */
    public static function name(string $data, int $offset): string
    {
        $raw = \substr($data, $offset, 8);
        $end = \strpos($raw, "\0");

        if ($end !== false) {
            $raw = \substr($raw, 0, $end);
        }

        return \strtoupper($raw);
    }
}
