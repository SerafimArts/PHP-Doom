<?php

declare(strict_types=1);

namespace Local\WAD\Exception;

final class MalformedLumpException extends WadException
{
    public static function becauseDataIsTruncated(string $lump, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Lump "%s" is truncated', $lump), 0, $prev);
    }

    public static function becauseSizeIsNotMultipleOfRecord(
        string $lump,
        int $size,
        int $recordSize,
        ?\Throwable $prev = null,
    ): self {
        return new self(
            \sprintf('Size %d of lump "%s" is not a multiple of the record size %d', $size, $lump, $recordSize),
            0,
            $prev,
        );
    }

    public static function becauseReferenceIsOutOfRange(
        string $lump,
        string $what,
        int $value,
        ?\Throwable $prev = null,
    ): self {
        return new self(\sprintf('Lump "%s" has %s out of range: %d', $lump, $what, $value), 0, $prev);
    }

    public static function becauseValueIsInvalid(string $lump, string $what, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Lump "%s" has an invalid %s', $lump, $what), 0, $prev);
    }
}
