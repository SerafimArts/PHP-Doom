<?php

declare(strict_types=1);

namespace Local\GPU\Exception;

final class ResourceException extends GpuException
{
    public static function becauseFileNotReadable(string $pathname, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Unable to read the file "%s"', $pathname), 0, $prev);
    }

    public static function becauseBufferIsNotMapped(?\Throwable $prev = null): self
    {
        return new self('The buffer is not host visible', 0, $prev);
    }

    public static function becauseDataDoesNotFit(int $size, int $capacity, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('%d bytes do not fit into %d bytes', $size, $capacity), 0, $prev);
    }
}
