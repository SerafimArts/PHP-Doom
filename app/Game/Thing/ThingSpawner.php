<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Thing;

use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Mobj\MissileState;
use Serafim\Doom\Game\Random;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;

/**
 * Puts the things of the map into the world for a skill level, and makes the things that appear while the game runs.
 */
final readonly class ThingSpawner
{
    private const int MULTIPLAYER_ONLY = 0x10;

    /**
     * `MTF_AMBUSH`: the monster waits deaf for the player to come into sight
     */
    private const int AMBUSH = 0x8;

    public function __construct(
        private MobjStates $states,
        public ThingCatalog $catalog,
    ) {}

    /**
     * @return list<Mobj>
     */
    public function spawn(Level $level, SectorState $sectors, int $skill, Random $random = new Random()): array
    {
        $mask = match (true) {
            $skill <= 2 => 0x1,
            $skill === 3 => 0x2,
            default => 0x4,
        };

        $things = [];

        foreach ($level->map->things as $thing) {
            if (($thing->flags & self::MULTIPLAYER_ONLY) !== 0 || ($thing->flags & $mask) === 0) {
                continue;
            }

            $definition = $this->catalog->findByType($thing->type);

            if ($definition === null) {
                continue;
            }

            $sector = $level->findSector($thing->x, $thing->y);
            $floor = (float) $sectors->floorHeights[$sector];
            $mobj = $this->create($definition, (float) $thing->x, (float) $thing->y, $floor, \deg2rad($thing->angle));
            $mobj->ceilingZ = (float) $sectors->ceilingHeights[$sector];
            $mobj->ai->isAmbush = ($thing->flags & self::AMBUSH) !== 0;

            if ($definition->isStartRandom && $mobj->stateTics > 0) {
                $mobj->stateTics = 1 + $random->nextByte() % $mobj->stateTics;
            }

            $things[] = $mobj;
        }

        return $things;
    }

    /**
     * Makes a thing of the definition standing on a floor.
     */
    public function create(ThingDefinition $definition, float $x, float $y, float $z, float $angle = 0.0): Mobj
    {
        $state = $definition->spawnState;

        $mobj = new Mobj(
            $definition->type,
            $x,
            $y,
            $z,
            $angle,
            $state->sprite,
            $state->frame,
            $state->isFullBright,
            $definition->radius,
            $definition->height,
            $definition->isSolid,
            $definition,
        );
        $mobj->health = $definition->health;
        $mobj->isShootable = $definition->isShootable;
        $mobj->floorZ = $z;
        $mobj->ai->reactionTime = $definition->reactionTime;

        if ($definition->isMissile) {
            $mobj->missile = new MissileState($definition->damage);
        }

        $this->states->set($mobj, $state);

        return $mobj;
    }
}
