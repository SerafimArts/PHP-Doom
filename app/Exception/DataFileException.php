<?php

declare(strict_types=1);

namespace Serafim\Doom\Exception;

final class DataFileException extends DoomException
{
    public static function becauseFileIsNotReadable(string $pathname, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('The data file "%s" cannot be read', $pathname), 0, $prev);
    }

    public static function becauseJsonIsInvalid(string $pathname, string $reason, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('The data file "%s" is not valid JSON: %s', $pathname, $reason), 0, $prev);
    }

    public static function becauseFieldIsInvalid(
        string $file,
        string $field,
        string $expected,
        ?\Throwable $prev = null,
    ): self {
        return new self(
            \sprintf('The field "%s" of the data file "%s" must be %s', $field, $file, $expected),
            0,
            $prev,
        );
    }

    public static function becauseReferenceIsUnknown(
        string $file,
        string $field,
        string $value,
        ?\Throwable $prev = null,
    ): self {
        return new self(
            \sprintf('The field "%s" of the data file "%s" refers to the unknown "%s"', $field, $file, $value),
            0,
            $prev,
        );
    }
}
