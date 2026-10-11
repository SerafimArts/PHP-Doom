<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Raytracing;

use Serafim\Doom\Render\Scene\SceneState;

/**
 * The things that put a shadow: the solid ones that stand, not the corpses and not the lamps whose light would shine
 * from inside of them. A caster is `float x, y, z, radius, height`, the box of the thing.
 */
final readonly class CasterBuilder
{
    public function __construct(
        /**
         * Thing type of the lamp
         */
        private int $lampThing,
    ) {}

    /**
     * @return array{string, int<0, max>} the packed casters and their number
     */
    public function build(SceneState $state): array
    {
        $casters = '';
        $count = 0;

        foreach ($state->things as $thing) {
            if (
                $thing->isCorpse
                || !$thing->isSolid
                || $thing->type === $this->lampThing
            ) {
                continue;
            }

            if ($count >= ShadowScene::MAX_INSTANCES) {
                break;
            }

            $casters .= \pack('g5', $thing->x, $thing->y, $thing->z, $thing->radius, $thing->height);
            ++$count;
        }

        return [$casters, $count];
    }
}
