<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Geometry;

use Local\WAD\Map\MapData;
use Local\WAD\Map\Sidedef;
use Serafim\Doom\Level\LinedefFlag;

/**
 * Lists the textured wall parts of a level, one part for every texture that a seg side has. A part is listed whenever
 * its texture is set, even if it has no height right now (the upper part of a closed door): sectors move.
 */
final readonly class WallBuilder
{
    private const int SCROLLING_SPECIAL = 48;

    /**
     * @return list<WallPart>
     */
    public function build(MapData $map): array
    {
        $parts = [];

        foreach ($map->segs as $index => $seg) {
            $linedef = $map->linedefs[$seg->linedef];

            $frontIndex = $linedef->rightSidedef;
            $backIndex = $linedef->leftSidedef;

            if ($seg->isOpposite) {
                $frontIndex = $linedef->leftSidedef;
                $backIndex = $linedef->rightSidedef;
            }

            if ($frontIndex === null) {
                continue;
            }

            $front = $map->sidedefs[$frontIndex];
            $back = null;

            if ($backIndex !== null) {
                $back = $map->sidedefs[$backIndex];
            }

            $start = $map->vertexes[$seg->startVertex];
            $end = $map->vertexes[$seg->endVertex];

            $lightBias = 0;

            if ($start->y === $end->y) {
                $lightBias = -1;
            } elseif ($start->x === $end->x) {
                $lightBias = 1;
            }

            foreach ($this->getTextures($front, $back) as [$type, $texture]) {
                $parts[] = new WallPart(
                    seg: $index,
                    sidedef: $frontIndex,
                    type: $type,
                    start: new Point($start->x, $start->y),
                    end: new Point($end->x, $end->y),
                    frontSector: $front->sector,
                    backSector: $back?->sector,
                    texture: $texture,
                    textureOffsetX: $seg->offset + $front->xOffset,
                    textureOffsetY: $front->yOffset,
                    isUnpegged: $this->isUnpegged($type, $linedef->flags),
                    lightBias: $lightBias,
                    isScrolling: $linedef->special === self::SCROLLING_SPECIAL,
                );
            }
        }

        return $parts;
    }

    /**
     * @return list<array{WallPartType, non-empty-string}>
     */
    private function getTextures(Sidedef $front, ?Sidedef $back): array
    {
        $candidates = [];

        if ($back !== null) {
            $candidates[] = [WallPartType::UPPER, $front->upperTexture];
            $candidates[] = [WallPartType::LOWER, $front->lowerTexture];
        }

        $candidates[] = [WallPartType::MIDDLE, $front->middleTexture];

        $textures = [];

        foreach ($candidates as [$type, $texture]) {
            if ($texture !== null && $texture !== '') {
                $textures[] = [$type, $texture];
            }
        }

        return $textures;
    }

    private function isUnpegged(WallPartType $type, int $flags): bool
    {
        return match ($type) {
            WallPartType::UPPER => ($flags & LinedefFlag::DONT_PEG_TOP) !== 0,
            WallPartType::LOWER, WallPartType::MIDDLE => ($flags & LinedefFlag::DONT_PEG_BOTTOM) !== 0,
        };
    }
}
