<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\MapData;
use Serafim\Doom\Level\Geometry\LevelGeometry;
use Serafim\Doom\Level\Geometry\PolygonBuilder;
use Serafim\Doom\Level\Geometry\WallBuilder;

final readonly class Level
{
    private function __construct(
        public MapData $map,
        public Bsp $bsp,
        /**
         * The sector of every subsector
         *
         * @var list<int<0, max>>
         */
        public array $subsectorSectors,
        public LevelGeometry $geometry,
        public LevelBounds $bounds,
        /**
         * The sectors that touch every sector through a line with two sides
         *
         * @var list<list<int<0, max>>>
         */
        public array $neighbors,
        /**
         * The sectors that the lines with the tag act on
         *
         * @var array<int, list<int<0, max>>>
         */
        public array $sectorsByTag,
        public LineGrid $lineGrid,
    ) {}

    public static function createFromMap(MapData $map): self
    {
        $bsp = new Bsp($map->nodes, \count($map->subsectors));
        $sectors = self::findSubsectorSectors($map);

        $bounds = LevelBounds::createFromVertexes($map->vertexes);

        return new self($map, $bsp, $sectors, new LevelGeometry(
            polygons: new PolygonBuilder()->build($map, $bsp, $sectors, $bounds),
            walls: new WallBuilder()->build($map),
        ), $bounds, self::findNeighbors($map), self::groupByTag($map), new LineGrid($map));
    }

    /**
     * @return int<0, max>
     */
    public function findSubsector(int $x, int $y): int
    {
        return $this->bsp->findSubsector($x, $y);
    }

    /**
     * @return int<0, max>
     */
    public function findSector(int $x, int $y): int
    {
        return $this->subsectorSectors[$this->bsp->findSubsector($x, $y)];
    }

    /**
     * The sector at a point of the map given in fractional units.
     *
     * @return int<0, max>
     */
    public function findSectorAt(float $x, float $y): int
    {
        return $this->findSector((int) \floor($x), (int) \floor($y));
    }

    /**
     * @return list<list<int<0, max>>>
     */
    private static function findNeighbors(MapData $map): array
    {
        $neighbors = \array_fill(0, \count($map->sectors), []);

        foreach ($map->linedefs as $linedef) {
            if ($linedef->leftSidedef === null) {
                continue;
            }

            $right = $map->sidedefs[$linedef->rightSidedef]->sector;
            $left = $map->sidedefs[$linedef->leftSidedef]->sector;

            if ($right === $left) {
                continue;
            }

            $neighbors[$right][$left] = $left;
            $neighbors[$left][$right] = $right;
        }

        return \array_map(\array_values(...), $neighbors);
    }

    /**
     * @return array<int, list<int<0, max>>>
     */
    private static function groupByTag(MapData $map): array
    {
        $groups = [];

        foreach ($map->sectors as $index => $sector) {
            if ($sector->tag !== 0) {
                $groups[$sector->tag][] = $index;
            }
        }

        return $groups;
    }

    /**
     * The sector of a subsector is the sector that its first seg faces.
     *
     * @return list<int<0, max>>
     */
    private static function findSubsectorSectors(MapData $map): array
    {
        $sectors = [];

        foreach ($map->subsectors as $subsector) {
            $seg = $map->segs[$subsector->firstSeg];
            $linedef = $map->linedefs[$seg->linedef];

            $sidedef = $linedef->rightSidedef;

            if ($seg->isOpposite) {
                $sidedef = $linedef->leftSidedef ?? $linedef->rightSidedef;
            }

            $sectors[] = $map->sidedefs[$sidedef]->sector;
        }

        return $sectors;
    }
}
