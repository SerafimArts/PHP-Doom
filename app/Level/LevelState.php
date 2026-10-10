<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\MapData;

/**
 * Everything of a level that the game changes and the renderer draws.
 */
final readonly class LevelState
{
    public function __construct(
        public SectorState $sectors,
        public SidedefState $sides,
    ) {}

    public static function createFromMap(MapData $map): self
    {
        return new self(SectorState::createFromMap($map), SidedefState::createFromMap($map));
    }
}
