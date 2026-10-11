<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Mesh;

use Serafim\Doom\Exception\AtlasException;
use Serafim\Doom\Level\Geometry\WallPartType;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Atlas\TextureAtlas;

final readonly class LevelMeshBuilder
{
    /**
     * @throws AtlasException in case of the atlas misses a texture of the level
     */
    public function build(Level $level, TextureAtlas $atlas): LevelMesh
    {
        $walls = '';
        $wallCount = 0;

        foreach ($level->geometry->walls as $wall) {
            $picture = $atlas->findNumber(LevelAtlasFactory::getTextureKey($wall->texture));

            if ($picture === null) {
                throw AtlasException::becausePictureNotFound($wall->texture);
            }

            $flags = 0;

            if ($wall->isUnpegged) {
                $flags |= LevelMesh::WALL_UNPEGGED;
            }

            if ($wall->isScrolling) {
                $flags |= LevelMesh::WALL_SCROLLING;
            }

            if ($wall->type === WallPartType::MIDDLE && $wall->backSector !== null) {
                $flags |= LevelMesh::WALL_MASKED;
            }

            $walls .= \pack(
                'f4V4f3Vl4',
                $wall->start->x,
                $wall->start->y,
                $wall->end->x,
                $wall->end->y,
                $wall->frontSector,
                $wall->backSector ?? LevelMesh::NO_SECTOR,
                $wall->type->value,
                $picture,
                $wall->textureOffsetX,
                $wall->textureOffsetY,
                $wall->getLength(),
                $flags,
                $wall->lightBias,
                0,
                0,
                0,
            );
            ++$wallCount;
        }

        $vertices = '';
        $vertexCount = 0;

        foreach ($level->geometry->polygons as $polygon) {
            $points = $polygon->points;

            for ($i = 1; $i < \count($points) - 1; ++$i) {
                foreach ([$points[0], $points[$i], $points[$i + 1]] as $point) {
                    $vertices .= \pack('f2V', $point->x, $point->y, $polygon->sector);
                    ++$vertexCount;
                }
            }
        }

        return new LevelMesh($walls, \min(4_294_967_295, $wallCount), $vertices, \min(4_294_967_295, $vertexCount));
    }
}
