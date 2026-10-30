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

        // The native functions of a platform are absent in the builds for the other platforms
        if (\PHP_OS_FAMILY !== 'Windows') {
            $content = \str_replace('void* glfwGetWin32Window(void* window);', '', $content);
        }

        if (\PHP_OS_FAMILY !== 'Darwin') {
            $content = \str_replace('void* glfwGetCocoaView(void* window);', '', $content);
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
            'darwin' => self::findDarwinLibrary(),
            default => throw GlfwNotSupportedException::becauseOsFamilyIsNotSupported(\PHP_OS_FAMILY),
        };
    }

    /**
     * GLFW is not bundled for macOS, the library of Homebrew (`brew install glfw`) is used instead.
     *
     * @return non-empty-string
     * @throws GlfwNotSupportedException in case of GLFW is not installed
     */
    private static function findDarwinLibrary(): string
    {
        foreach (['/opt/homebrew/lib/libglfw.3.dylib', '/usr/local/lib/libglfw.3.dylib'] as $pathname) {
            if (\is_file($pathname)) {
                return $pathname;
            }
        }

        throw GlfwNotSupportedException::becauseOsFamilyIsNotSupported(\PHP_OS_FAMILY);
    }
}
