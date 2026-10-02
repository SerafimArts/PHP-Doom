<?php

declare(strict_types=1);

namespace Local\GLFW;

final readonly class GLFW3
{
    public static function load(): \FFI
    {
        return \FFI::cdef(
            self::getHeaderContent(),
            self::getBinPathname(),
        );
    }

    private static function getHeaderContent(): string
    {
        $content = \file_get_contents($pathname = self::getHeaderPathname());

        if ($content === false) {
            throw new \RuntimeException(\sprintf('Unable to read GLFW header "%s"', $pathname));
        }

        return $content;
    }

    /**
     * @return non-empty-string
     */
    private static function getHeaderPathname(): string
    {
        return __DIR__ . '/../resources/glfw3.h';
    }

    /**
     * @return non-empty-string
     */
    private static function getBinPathname(): string
    {
        return match (\strtolower(\PHP_OS_FAMILY)) {
            'windows' => __DIR__ . '/../bin/glfw3.dll',
            default => throw new \RuntimeException('Unsupported OS family'),
        };
    }
}
