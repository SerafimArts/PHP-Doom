<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data\Tuning;

use Serafim\Doom\Level\Data\DataRecord;

/**
 * The ranges, the spread and the damage rules shared by the player and the monsters (`p_local.h`, `p_inter.c`).
 */
final readonly class CombatTuning
{
    public function __construct(
        /**
         * `MELEERANGE`
         */
        public float $meleeRange,
        /**
         * `MISSILERANGE`
         */
        public float $missileRange,
        /**
         * The spread of a shot of the player is a full circle divided by it
         *
         * @var int<1, max>
         */
        public int $playerSpreadDivisor,
        /** @var int<1, max> */
        public int $monsterSpreadDivisor,
        /**
         * The slope of the aim of a shot that sees no target
         */
        public float $aimSlope,
        public float $wallBackoff,
        public float $thingBackoff,
        /**
         * How many tics a monster keeps its target when something else hurts it (`BASETHRESHOLD`)
         */
        public int $baseThreshold,
        public int $barrelDamage,
    ) {}

    public static function createFromRecord(DataRecord $record): self
    {
        return new self(
            $record->getFloat('meleeRange'),
            $record->getFloat('missileRange'),
            \max(1, $record->getInt('playerSpreadDivisor')),
            \max(1, $record->getInt('monsterSpreadDivisor')),
            $record->getFloat('aimSlope'),
            $record->getFloat('wallBackoff'),
            $record->getFloat('thingBackoff'),
            $record->getInt('baseThreshold'),
            $record->getInt('barrelDamage'),
        );
    }

    /**
     * Angle of the spread of one unit of the difference of two random bytes of a shot of the player.
     */
    public function getPlayerSpreadStep(): float
    {
        return 2 * \M_PI / $this->playerSpreadDivisor;
    }

    public function getMonsterSpreadStep(): float
    {
        return 2 * \M_PI / $this->monsterSpreadDivisor;
    }
}
