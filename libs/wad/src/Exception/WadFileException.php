<?php

declare(strict_types=1);

namespace Local\WAD\Exception;

final class WadFileException extends WadException
{
    public static function becauseFileIsNotReadable(string $pathname, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('WAD file "%s" does not exist or is not readable', $pathname), 0, $prev);
    }
}
