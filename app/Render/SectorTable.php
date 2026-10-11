<?php

declare(strict_types=1);

namespace Serafim\Doom\Render;

use Serafim\Doom\Exception\AtlasException;
use Serafim\Doom\Level\SectorState;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Atlas\TextureAtlas;

/**
 * The sectors as the shaders read them: heights, light and the flats come from the {@see SectorState} that the game
 * changes. The host rewrites the whole table every frame, it is only a few hundred bytes.
 */
final class SectorTable
{
    public const int SECTOR_SIZE = 32;

    public const int FLOOR_IS_SKY = 1;

    public const int CEILING_IS_SKY = 2;

    private function __construct(
        /**
         * Numbers of the flat pictures by the names of the flats
         *
         * @var array<string, int<0, max>>
         */
        private readonly array $flats,
        /** @var int<0, max> */
        private readonly int $sky,
        /** @var int<0, max> */
        private readonly int $sectors,
    ) {}

    /**
     * @param int<0, max> $sectors
     *
     * @throws AtlasException in case of the atlas has no sky
     */
    public static function create(int $sectors, TextureAtlas $atlas): self
    {
        $flats = [];

        foreach ($atlas->getNumbers() as $key => $number) {
            if (\str_starts_with($key, 'F:')) {
                $flats[\substr($key, 2)] = $number;
            }
        }

        return new self(
            $flats,
            $atlas->getNumber(LevelAtlasFactory::getTextureKey(LevelAtlasFactory::SKY_TEXTURE)),
            $sectors,
        );
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return $this->sectors;
    }

    public function pack(SectorState $state): string
    {
        $data = '';

        for ($index = 0; $index < $this->sectors; ++$index) {
            $floor = $state->floorFlats[$index];
            $ceiling = $state->ceilingFlats[$index];
            $flags = 0;

            if ($floor === SectorState::SKY_FLAT) {
                $flags |= self::FLOOR_IS_SKY;
            }

            if ($ceiling === SectorState::SKY_FLAT) {
                $flags |= self::CEILING_IS_SKY;
            }

            $data .= \pack(
                'f2V6',
                $state->floorHeights[$index],
                $state->ceilingHeights[$index],
                $state->lights[$index],
                $this->flats[$floor] ?? $this->sky,
                $this->flats[$ceiling] ?? $this->sky,
                $flags,
                0,
                0,
            );
        }

        return $data;
    }
}
