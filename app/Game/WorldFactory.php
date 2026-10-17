<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

use Local\WAD\Map\Thing;
use Serafim\Doom\Game\Combat\DamageSystem;
use Serafim\Doom\Game\Combat\Hitscan;
use Serafim\Doom\Game\Combat\Impacts;
use Serafim\Doom\Game\Combat\WeaponSystem;
use Serafim\Doom\Game\Monster\MissileSystem;
use Serafim\Doom\Game\Monster\MonsterAttacks;
use Serafim\Doom\Game\Monster\MonsterBrain;
use Serafim\Doom\Game\Monster\MonsterMover;
use Serafim\Doom\Game\Monster\NoiseAlert;
use Serafim\Doom\Game\Monster\RadiusAttack;
use Serafim\Doom\Game\Monster\Sight;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\LineTable;
use Serafim\Doom\Game\Physics\PlayerPhysics;
use Serafim\Doom\Game\Special\LineSpecials;
use Serafim\Doom\Game\Special\PlaneMover;
use Serafim\Doom\Game\Special\SectorSpecials;
use Serafim\Doom\Game\Special\SpecialContext;
use Serafim\Doom\Game\Special\ThinkerList;
use Serafim\Doom\Game\Thing\MobjStates;
use Serafim\Doom\Game\Thing\PickupSystem;
use Serafim\Doom\Game\Thing\ThingAnimator;
use Serafim\Doom\Game\Thing\ThingSpawner;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LevelState;

/**
 * Puts a level into play: spawns the player and the things, and connects the systems of the game.
 */
final readonly class WorldFactory
{
    private const int PLAYER_START = 1;

    public function __construct(
        private GameData $data,
    ) {}

    /**
     * @param list<string> $textureNames the names of the wall textures that exist, they tell which switches can turn
     * @param int<0, max> $parSeconds the par time of the level in seconds
     */
    public function create(
        Level $level,
        array $textureNames = [],
        int $seed = 1,
        int $skill = 3,
        int $parSeconds = 30,
    ): World {
        $data = $this->data;
        $tuning = $data->tuning;
        $state = LevelState::createFromMap($level->map);
        $sectors = $state->sectors;
        $player = $this->spawnPlayer($level, $state);
        $stats = new PlayerStats();

        $random = new Random($seed);
        $states = new MobjStates($data->mobjStates);
        $spawner = new ThingSpawner($states, $data->things);
        $things = new ThingRegistry($spawner->spawn($level, $sectors, $skill, $random));

        $context = new GameContext($level, $sectors, $random, $states, $spawner, $things, $player, $stats);

        $hitscan = new Hitscan($context, $tuning->combat);
        $damage = new DamageSystem($context, $tuning->combat);
        $impacts = new Impacts($context);

        $lineTable = new LineTable($level);
        $sight = new Sight($level, $sectors, $lineTable, $tuning->monster);
        $noise = new NoiseAlert($lineTable, $sectors);

        $weapons = new WeaponSystem(
            $context,
            $hitscan,
            $damage,
            $impacts,
            $data->weaponStates,
            $data->weapons,
            $tuning,
            static fn() => $noise->alert($level->findSectorAt($player->x, $player->y)),
        );
        $weapons->bringUp();

        $collider = new Collider($level, $sectors, $things);
        $thinkers = new ThinkerList();

        $special = new SpecialContext(
            $level,
            $sectors,
            new PlaneMover($sectors, $collider, $player),
            $thinkers,
            $random,
            $data->specials,
            $data->animations,
        );

        $lines = new LineSpecials(
            $special,
            $state->sides,
            \array_fill_keys(\array_map(\strtoupper(...), $textureNames), true),
        );
        $sectorSpecials = new SectorSpecials($special);
        $sectorSpecials->spawn();

        $missiles = new MissileSystem($context, $collider, $damage, $tuning->monster);

        $states->setHandler(new MonsterBrain(
            $context,
            $noise,
            new MonsterMover($collider, $lines, $player, $random, $tuning->monster),
            new MonsterAttacks(
                $context,
                $hitscan,
                $impacts,
                $damage,
                $missiles,
                $sight,
                $tuning->combat,
                $tuning->monster,
            ),
            new RadiusAttack($context, $damage, $sight, $tuning->combat),
            $tuning->combat,
            $tuning->monster,
        ));

        return new World(
            $context,
            $state,
            new GameSystems(
                new PlayerPhysics($collider, $tuning->physics),
                $weapons,
                new PickupSystem(),
                $missiles,
                new ThingAnimator($states),
                $thinkers,
                $lines,
                $sectorSpecials,
            ),
            $data->animations,
            $parSeconds,
        );
    }

    private function spawnPlayer(Level $level, LevelState $state): Player
    {
        $start = $this->findPlayerStart($level) ?? new Thing(0, 0, 0, self::PLAYER_START, 0);
        $floor = $state->sectors->floorHeights[$level->findSector($start->x, $start->y)];

        return new Player($start->x, $start->y, $floor, \deg2rad($start->angle));
    }

    private function findPlayerStart(Level $level): ?Thing
    {
        foreach ($level->map->things as $thing) {
            if ($thing->type === self::PLAYER_START) {
                return $thing;
            }
        }

        return null;
    }
}
