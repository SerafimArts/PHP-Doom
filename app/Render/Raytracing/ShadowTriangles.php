<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Raytracing;

use Serafim\Doom\Level\Geometry\WallPartType;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;
use Serafim\Doom\Render\Mesh\LevelMesh;

/**
 * The solid surfaces of a level as triangles for ray queries: walls, floors and ceilings at the heights the level has
 * on load. See-through middle textures and sky ceilings let the light through.
 */
final readonly class ShadowTriangles
{
    public function __construct(
        /**
         * `float` triples
         */
        public string $vertices,
        /** @var int<0, 4294967295> */
        public int $vertexCount,
        /**
         * `uint32_t` indices, three per triangle
         */
        public string $indices,
        /** @var int<0, 4294967295> */
        public int $triangleCount,
    ) {}

    /**
     * @param array<int, true> $dynamic the sectors that move, what depends on their heights is left out
     */
    public static function createFromLevel(Level $level, LevelMesh $mesh, array $dynamic = []): self
    {
        $sectors = $level->map->sectors;

        /** @var list<float> $vertices */
        $vertices = [];
        /** @var list<int> $indices */
        $indices = [];

        for ($i = 0; $i < $mesh->wallCount; ++$i) {
            $offset = $i * LevelMesh::WALL_SIZE;

            $frontSector = self::readUint($mesh->walls, $offset + 16);
            $backSector = self::readUint($mesh->walls, $offset + 20);
            $type = self::readUint($mesh->walls, $offset + 24);
            $flags = self::readUint($mesh->walls, $offset + 48);

            if (
                ($flags & LevelMesh::WALL_MASKED) !== 0
                || isset($dynamic[$frontSector])
                || isset($dynamic[$backSector])
            ) {
                continue;
            }

            $front = $sectors[$frontSector];
            $bottom = (float) $front->floorHeight;
            $top = (float) $front->ceilingHeight;

            if ($backSector !== LevelMesh::NO_SECTOR) {
                $back = $sectors[$backSector];

                if ($type === WallPartType::UPPER->value) {
                    $bottom = (float) $back->ceilingHeight;

                    if (
                        $front->ceilingTexture === SectorState::SKY_FLAT
                        && $back->ceilingTexture === SectorState::SKY_FLAT
                    ) {
                        continue;
                    }
                } elseif ($type === WallPartType::LOWER->value) {
                    $top = (float) \max($back->floorHeight, $front->floorHeight);
                } else {
                    continue;
                }
            }

            if ($top <= $bottom) {
                continue;
            }

            $x1 = self::readFloat($mesh->walls, $offset);
            $y1 = self::readFloat($mesh->walls, $offset + 4);
            $x2 = self::readFloat($mesh->walls, $offset + 8);
            $y2 = self::readFloat($mesh->walls, $offset + 12);

            $first = \intdiv(\count($vertices), 3);

            \array_push($vertices, $x1, $y1, $bottom, $x2, $y2, $bottom, $x2, $y2, $top, $x1, $y1, $top);
            \array_push($indices, $first, $first + 1, $first + 2, $first, $first + 2, $first + 3);
        }

        for ($i = 0; $i < $mesh->flatVertexCount; ++$i) {
            $offset = $i * LevelMesh::FLAT_VERTEX_SIZE;

            $x = self::readFloat($mesh->flatVertices, $offset);
            $y = self::readFloat($mesh->flatVertices, $offset + 4);
            $sectorIndex = self::readUint($mesh->flatVertices, $offset + 8);
            $sector = $sectors[$sectorIndex];

            $indices[] = \intdiv(\count($vertices), 3);

            if (isset($dynamic[$sectorIndex])) {
                \array_push($vertices, 0.0, 0.0, 0.0);

                continue;
            }

            \array_push($vertices, $x, $y, (float) $sector->floorHeight);
        }

        for ($i = 0; $i < $mesh->flatVertexCount; ++$i) {
            $offset = $i * LevelMesh::FLAT_VERTEX_SIZE;

            $x = self::readFloat($mesh->flatVertices, $offset);
            $y = self::readFloat($mesh->flatVertices, $offset + 4);
            $sectorIndex = self::readUint($mesh->flatVertices, $offset + 8);
            $sector = $sectors[$sectorIndex];

            $indices[] = \intdiv(\count($vertices), 3);

            if ($sector->ceilingTexture === SectorState::SKY_FLAT || isset($dynamic[$sectorIndex])) {
                \array_push($vertices, 0.0, 0.0, 0.0);

                continue;
            }

            \array_push($vertices, $x, $y, (float) $sector->ceilingHeight);
        }

        $vertexCount = \intdiv(\count($vertices), 3);
        $triangleCount = \intdiv(\count($indices), 3);
        \assert($vertexCount >= 0 && $vertexCount <= 4_294_967_295);
        \assert($triangleCount >= 0 && $triangleCount <= 4_294_967_295);

        return new self(\pack('g*', ...$vertices), $vertexCount, \pack('V*', ...$indices), $triangleCount);
    }

    /**
     * @return int<0, 4294967295>
     */
    public static function readUint(string $bytes, int $offset): int
    {
        $value = \unpack('Vvalue', $bytes, $offset)['value'] ?? null;
        \assert(\is_int($value) && $value >= 0 && $value <= 4_294_967_295);

        return $value;
    }

    public static function readFloat(string $bytes, int $offset): float
    {
        $value = \unpack('gvalue', $bytes, $offset)['value'] ?? null;
        \assert(\is_float($value));

        return $value;
    }
}
