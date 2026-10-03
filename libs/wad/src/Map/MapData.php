<?php

declare(strict_types=1);

namespace Local\WAD\Map;

final readonly class MapData
{
    public function __construct(
        /**
         * Name of the level in upper case (E1M1)
         */
        public string $name,
        /** @var list<Thing> */
        public array $things,
        /** @var list<Linedef> */
        public array $linedefs,
        /** @var list<Sidedef> */
        public array $sidedefs,
        /** @var list<Vertex> */
        public array $vertexes,
        /** @var list<Seg> */
        public array $segs,
        /** @var list<Subsector> */
        public array $subsectors,
        /** @var list<Node> */
        public array $nodes,
        /** @var list<Sector> */
        public array $sectors,
        /**
         * The visibility table as it is stored: a bit per pair of sectors, empty if the level has none
         */
        public string $reject,
        /**
         * {@see null} if the level has no blockmap
         */
        public ?Blockmap $blockmap,
    ) {}
}
