<?php

declare(strict_types=1);

namespace Local\GLFW\Exception;

final class GlfwNotSupportedException extends GlfwException
{
    public static function becauseOsFamilyIsNotSupported(string $family, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('GLFW is not bundled for the OS family "%s"', $family), 0, $prev);
    }

    public static function becauseHeaderIsNotReadable(string $pathname, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Unable to read the GLFW header "%s"', $pathname), 0, $prev);
    }

    public static function becauseLibraryIsNotInstalled(string $package, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('The library "%s" is not installed (try "brew install %s")', $package, $package), 0, $prev);
    }
}
