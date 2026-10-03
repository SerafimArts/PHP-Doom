<?php

declare(strict_types=1);

namespace Local\GLFW;

use Local\GLFW\Exception\GlfwNotSupportedException;

final readonly class GLFW3
{
    /**
     * @throws GlfwNotSupportedException in case of the OS has no bundled library or the header is not readable
     */
    public static function load(): \FFI
    {
        return \FFI::cdef(
            self::getHeaderContent(),
            self::getBinPathname(),
        );
    }

    /**
     * @throws GlfwNotSupportedException in case of the header is not readable
     */
    private static function getHeaderContent(): string
    {
        $content = \file_get_contents($pathname = self::getHeaderPathname());

        if ($content === false) {
            throw GlfwNotSupportedException::becauseHeaderIsNotReadable($pathname);
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
     * @throws GlfwNotSupportedException in case of the OS has no bundled library
     */
    private static function getBinPathname(): string
    {
        return match (\strtolower(\PHP_OS_FAMILY)) {
            'windows' => __DIR__ . '/../bin/glfw3.dll',
            default => throw GlfwNotSupportedException::becauseOsFamilyIsNotSupported(\PHP_OS_FAMILY),
        };
    }
}
