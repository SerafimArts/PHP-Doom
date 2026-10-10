<?php

declare(strict_types=1);

namespace Serafim\Doom\Exception;

final class AtlasException extends DoomException
{
    public static function becausePictureNotFound(string $key, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('The atlas has no picture "%s"', $key), 0, $prev);
    }

    public static function becauseAtlasIsFull(string $key, int $size, ?\Throwable $prev = null): self
    {
        return new self(
            \sprintf('Picture "%s" does not fit into the atlas of %dx%d texels', $key, $size, $size),
            0,
            $prev,
        );
    }

    public static function becausePictureIsEmpty(string $key, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Picture "%s" has no texels', $key), 0, $prev);
    }
}
