<?php

declare(strict_types=1);

namespace Serafim\Doom\Exception;

final class DownloadException extends DoomException
{
    public static function becauseDownloadFailed(string $url, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Unable to download "%s"', $url), 0, $prev);
    }

    public static function becauseChecksumMismatch(string $pathname, string $expected, string $actual): self
    {
        return new self(\sprintf('Checksum of "%s" is %s, expected %s', $pathname, $actual, $expected));
    }

    public static function becauseArchiveNotExtracted(string $pathname, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Unable to extract the archive "%s"', $pathname), 0, $prev);
    }

    public static function becauseCloneFailed(string $url, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Unable to clone "%s"', $url), 0, $prev);
    }
}
