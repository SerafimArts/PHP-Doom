<?php

declare(strict_types=1);

namespace Local\GPU\Exception;

final class WindowException extends GpuException
{
    public static function becauseWindowNotCreated(string $details, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Unable to create a window: %s', $details), 0, $prev);
    }

    public static function becauseGlfwNotInitialized(?\Throwable $prev = null): self
    {
        return new self('Unable to initialize GLFW', 0, $prev);
    }

    public static function becauseSurfaceNotCreated(int $result, string $details, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Unable to create a surface (VkResult %d): %s', $result, $details), 0, $prev);
    }

    public static function becausePlatformNotSupported(string $family, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('The platform "%s" is not supported', $family), 0, $prev);
    }
}
