<?php

declare(strict_types=1);

namespace Serafim\Doom\Exception;

final class ShaderCompilationException extends DoomException
{
    public static function becauseCompilerNotFound(?\Throwable $prev = null): self
    {
        return new self('glslc not found: install the Vulkan SDK and add it to PATH or VULKAN_SDK', 0, $prev);
    }

    public static function becauseShaderIsInvalid(string $source, string $output, ?\Throwable $prev = null): self
    {
        return new self(\sprintf("Unable to compile \"%s\":\n%s", $source, $output), 0, $prev);
    }
}
