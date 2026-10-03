<?php

declare(strict_types=1);

namespace Local\WAD\Exception;

final class LumpNotFoundException extends WadException
{
    public static function becauseLumpDoesNotExist(string $name, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Lump "%s" does not exist in the WAD', $name), 0, $prev);
    }

    public static function becauseTextureDoesNotExist(string $name, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Texture "%s" is not defined in the WAD', $name), 0, $prev);
    }

    public static function becauseMapLumpDoesNotExist(string $map, string $lump, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Level "%s" has no %s lump', $map, $lump), 0, $prev);
    }

    public static function becauseMarkerDoesNotExist(string $name, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('Marker lump "%s" does not exist in the WAD', $name), 0, $prev);
    }
}
