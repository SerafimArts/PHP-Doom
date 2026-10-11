<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Raytracing;

use Serafim\Doom\Level\Geometry\WallPartType;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;
use Serafim\Doom\Render\Mesh\LevelMesh;

/**
 * The surfaces that depend on the height of a moving sector: its floor and ceiling and the walls around it. The
 * triangles keep their shape for the whole level, only the heights of the vertices follow the sectors.
 */
final readonly class DynamicTriangles
{
    private function __construct(
        /**
         * x1, y1, x2, y2, front sector, back sector (`NO_SECTOR` for none), type of the wall part
         *
         * @var list<array{float, float, float, float, int, int, int}>
         */
        private array $walls,
        /**
         * x, y, sector, 1 for a ceiling and 0 for a floor
         *
         * @var list<array{float, float, int, int}>
         */
        private array $flats,
        /**
         * @var array<int, bool>
         */
        private array $isSkyCeiling,
        /** @var int<0, 4294967295> */
        public int $vertexCount,
        /** @var int<0, 4294967295> */
        public int $triangleCount,
        /**
         * `uint32_t` indices, three per triangle
         */
        public string $indices,
    ) {}

    /**
     * @param array<int, true> $dynamic
     */
    public static function create(Level $level, LevelMesh $mesh, array $dynamic): self
    {
        $isSkyCeiling = [];

        foreach ($level->map->sectors as $index => $sector) {
            $isSkyCeiling[$index] = $sector->ceilingTexture === SectorState::SKY_FLAT;
        }

        $walls = [];

        for ($i = 0; $i < $mesh->wallCount; ++$i) {
            $offset = $i * LevelMesh::WALL_SIZE;

            $front = ShadowTriangles::readUint($mesh->walls, $offset + 16);
            $back = ShadowTriangles::readUint($mesh->walls, $offset + 20);
            $flags = ShadowTriangles::readUint($mesh->walls, $offset + 48);

            if (($flags & LevelMesh::WALL_MASKED) !== 0 || (!isset($dynamic[$front]) && !isset($dynamic[$back]))) {
                continue;
            }

            $walls[] = [
                ShadowTriangles::readFloat($mesh->walls, $offset),
                ShadowTriangles::readFloat($mesh->walls, $offset + 4),
                ShadowTriangles::readFloat($mesh->walls, $offset + 8),
                ShadowTriangles::readFloat($mesh->walls, $offset + 12),
                $front,
                $back,
                ShadowTriangles::readUint($mesh->walls, $offset + 24),
            ];
        }

        $floors = [];
        $ceilings = [];

        for ($i = 0; $i < $mesh->flatVertexCount; ++$i) {
            $offset = $i * LevelMesh::FLAT_VERTEX_SIZE;
            $sector = ShadowTriangles::readUint($mesh->flatVertices, $offset + 8);

            if (!isset($dynamic[$sector])) {
                continue;
            }

            $x = ShadowTriangles::readFloat($mesh->flatVertices, $offset);
            $y = ShadowTriangles::readFloat($mesh->flatVertices, $offset + 4);

            $floors[] = [$x, $y, $sector, 0];
            $ceilings[] = [$x, $y, $sector, 1];
        }

        $flats = \array_merge($floors, $ceilings);

        $indices = [];

        foreach ($walls as $index => $_) {
            $first = $index * 4;

            \array_push($indices, $first, $first + 1, $first + 2, $first, $first + 2, $first + 3);
        }

        $wallVertices = \count($walls) * 4;

        foreach ($flats as $index => $_) {
            $indices[] = $wallVertices + $index;
        }

        $vertexCount = $wallVertices + \count($flats);
        $triangleCount = \intdiv(\count($indices), 3);
        \assert($vertexCount <= 4_294_967_295);
        \assert($triangleCount >= 0 && $triangleCount <= 4_294_967_295);

        $packedIndices = '';

        if ($indices !== []) {
            $packedIndices = \pack('V*', ...$indices);
        }

        return new self($walls, $flats, $isSkyCeiling, $vertexCount, $triangleCount, $packedIndices);
    }

    public function isEmpty(): bool
    {
        return $this->triangleCount === 0;
    }

    /**
     * The vertices at the heights that the sectors have now: `float` triples.
     */
    public function packVertices(SectorState $sectors): string
    {
        $floors = $sectors->floorHeights;
        $ceilings = $sectors->ceilingHeights;

        $vertices = [];

        foreach ($this->walls as [$x1, $y1, $x2, $y2, $front, $back, $type]) {
            $bottom = (float) $floors[$front];
            $top = (float) $ceilings[$front];

            if ($back !== LevelMesh::NO_SECTOR) {
                if ($type === WallPartType::UPPER->value) {
                    $bottom = (float) $ceilings[$back];

                    if (($this->isSkyCeiling[$front] ?? false) && ($this->isSkyCeiling[$back] ?? false)) {
                        $top = $bottom;
                    }
                } elseif ($type === WallPartType::LOWER->value) {
                    $top = (float) \max($floors[$back], $floors[$front]);
                } else {
                    $top = $bottom;
                }
            }

            if ($top < $bottom) {
                $top = $bottom;
            }

            \array_push($vertices, $x1, $y1, $bottom, $x2, $y2, $bottom, $x2, $y2, $top, $x1, $y1, $top);
        }

        foreach ($this->flats as [$x, $y, $sector, $isCeiling]) {
            if ($isCeiling === 0) {
                \array_push($vertices, $x, $y, (float) $floors[$sector]);

                continue;
            }

            if ($this->isSkyCeiling[$sector] ?? false) {
                \array_push($vertices, $x, $y, (float) $floors[$sector]);

                continue;
            }

            \array_push($vertices, $x, $y, (float) $ceilings[$sector]);
        }

        if ($vertices === []) {
            return '';
        }

        return \pack('g*', ...$vertices);
    }
}
