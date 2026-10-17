<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

use Serafim\Doom\Game\Data\StateData;
use Serafim\Doom\Game\Mobj\MissileState;
use Serafim\Doom\Game\Mobj\MonsterState;
use Serafim\Doom\Game\Thing\StateAction;
use Serafim\Doom\Game\Thing\ThingDefinition;

/**
 * A thing of the level that stands in the world and has a sprite: a decoration, an item to pick up, a monster. The
 * game changes the frame of the sprite (the animation) and the renderer reads it.
 */
final class Mobj
{
    /**
     * The thing is picked up or destroyed and no longer part of the world
     */
    public bool $isRemoved = false;

    /**
     * The state of the thing in the state machine, {@see null} once the thing is removed
     *
     * @var StateData<StateAction>|null
     */
    public ?StateData $state = null;

    /**
     * Tics left in the state, -1 for ever
     */
    public int $stateTics = 0;

    /**
     * Hit points, a thing that can be shot dies when they are gone
     */
    public int $health = 0;

    /**
     * Bullets hit the thing
     */
    public bool $isShootable = false;

    /**
     * What is left of a thing that was killed
     */
    public bool $isCorpse = false;

    /**
     * Left by a monster that died: gives the player half of what an item placed on the map gives
     */
    public bool $isDropped = false;

    /**
     * Vertical speed in units per tic, for what rises (bullet puffs) or falls (blood)
     */
    public float $momentumZ = 0.0;

    /**
     * The floor under the thing, what falling things land on
     */
    public float $floorZ = 0.0;

    /**
     * The ceiling over the thing
     */
    public float $ceilingZ = 0.0;

    /**
     * Horizontal speed in units per tic, for what flies
     */
    public float $momentumX = 0.0;

    public float $momentumY = 0.0;

    /**
     * What the thing decides while it hunts, only monsters use it
     */
    public readonly MonsterState $ai;

    /**
     * What the thing carries while it flies, {@see null} for what is not a missile
     */
    public ?MissileState $missile = null;

    public function __construct(
        /**
         * The number of the thing in the map (3004 is a zombieman)
         */
        public readonly int $type,
        public float $x,
        public float $y,
        /**
         * Height of the feet
         */
        public float $z,
        /**
         * Counter-clockwise from the east, in radians
         */
        public float $angle,
        /**
         * The four letters of the sprite
         *
         * @var non-empty-string
         */
        public string $sprite,
        /**
         * The letter of the frame that is shown
         *
         * @var non-empty-string
         */
        public string $frame,
        /**
         * Shown at full brightness whatever the light of the sector
         */
        public bool $isFullBright,
        public readonly float $radius,
        public float $height,
        /**
         * Players and monsters cannot walk through it
         */
        public bool $isSolid,
        public readonly ?ThingDefinition $definition = null,
    ) {
        $this->ai = new MonsterState();
    }
}
