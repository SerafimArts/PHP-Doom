<?php

declare(strict_types=1);

namespace Local\WAD\Exception;

final class MalformedWadException extends WadException
{
    public static function becauseHeaderIsInvalid(string $source, ?\Throwable $prev = null): self
    {
        return new self(
            \sprintf('"%s" is not a WAD file: the header is missing or has an unknown signature', $source),
            0,
            $prev,
        );
    }

    public static function becauseDirectoryIsOutOfFile(string $source, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('WAD "%s" is truncated: the lump directory is out of the file', $source), 0, $prev);
    }

    public static function becauseLumpIsOutOfFile(string $source, string $lump, ?\Throwable $prev = null): self
    {
        return new self(
            \sprintf('WAD "%s" is truncated: the data of lump "%s" is out of the file', $source, $lump),
            0,
            $prev,
        );
    }
}
