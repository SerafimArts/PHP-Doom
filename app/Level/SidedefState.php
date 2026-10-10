<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\MapData;
use Serafim\Doom\Level\Geometry\WallPartType;

/**
 * The textures of the sidedefs while the game runs: a switch changes the texture of its wall when it is used.
 */
final class SidedefState
{
    /**
     * Grows with every change, so the renderer knows when to read the textures again
     *
     * @var int<0, max>
     */
    private int $version = 0;

    private function __construct(
        /** @var array<int, string|null> */
        private array $upper,
        /** @var array<int, string|null> */
        private array $lower,
        /** @var array<int, string|null> */
        private array $middle,
    ) {}

    public static function createFromMap(MapData $map): self
    {
        $upper = [];
        $lower = [];
        $middle = [];

        foreach ($map->sidedefs as $sidedef) {
            $upper[] = $sidedef->upperTexture;
            $lower[] = $sidedef->lowerTexture;
            $middle[] = $sidedef->middleTexture;
        }

        return new self($upper, $lower, $middle);
    }

    /**
     * @return int<0, max>
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    public function findTexture(int $sidedef, WallPartType $type): ?string
    {
        return match ($type) {
            WallPartType::UPPER => $this->upper[$sidedef] ?? null,
            WallPartType::LOWER => $this->lower[$sidedef] ?? null,
            WallPartType::MIDDLE => $this->middle[$sidedef] ?? null,
        };
    }

    public function setTexture(int $sidedef, WallPartType $type, ?string $texture): void
    {
        match ($type) {
            WallPartType::UPPER => $this->upper[$sidedef] = $texture,
            WallPartType::LOWER => $this->lower[$sidedef] = $texture,
            WallPartType::MIDDLE => $this->middle[$sidedef] = $texture,
        };

        ++$this->version;
    }
}
