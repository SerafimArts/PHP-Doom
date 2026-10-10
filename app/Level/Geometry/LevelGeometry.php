<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Geometry;

final readonly class LevelGeometry
{
    public function __construct(
        /**
         * Floors and ceilings, by the index of a subsector
         *
         * @var list<SubsectorPolygon>
         */
        public array $polygons,
        /** @var list<WallPart> */
        public array $walls,
    ) {}
}
