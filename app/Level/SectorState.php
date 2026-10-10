<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\MapData;

/**
 * What changes in the sectors while the game runs: heights, light, the flats and the special. The game moves doors and
 * lifts and flickers the lights here, physics and the renderer read it.
 */
final class SectorState
{
    /**
     * The flat that marks the sky (`F_SKY1`), the floor or the ceiling of a sector shows the sky
     */
    public const string SKY_FLAT = 'F_SKY1';

    private function __construct(
        /** @var array<int, int|float> */
        public array $floorHeights,
        /** @var array<int, int|float> */
        public array $ceilingHeights,
        /** @var array<int, int> */
        public array $lights,
        /** @var array<int, string> */
        public array $floorFlats,
        /** @var array<int, string> */
        public array $ceilingFlats,
        /**
         * The special that is still to be handled: a sector that got its light effect or was counted as a secret has 0
         *
         * @var array<int, int>
         */
        public array $specials,
    ) {}

    public static function createFromMap(MapData $map): self
    {
        $floors = [];
        $ceilings = [];
        $lights = [];
        $floorFlats = [];
        $ceilingFlats = [];
        $specials = [];

        foreach ($map->sectors as $sector) {
            $floors[] = $sector->floorHeight;
            $ceilings[] = $sector->ceilingHeight;
            $lights[] = $sector->lightLevel;
            $floorFlats[] = $sector->floorTexture;
            $ceilingFlats[] = $sector->ceilingTexture;
            $specials[] = $sector->special;
        }

        return new self($floors, $ceilings, $lights, $floorFlats, $ceilingFlats, $specials);
    }
}
