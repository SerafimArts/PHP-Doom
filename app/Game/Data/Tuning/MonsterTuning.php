<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data\Tuning;

use Serafim\Doom\Level\Data\DataRecord;

/**
 * The movement and the attacks of the monsters (`p_enemy.c`).
 */
final readonly class MonsterTuning
{
    public function __construct(
        public float $missileNear,
        public float $noClawBonus,
        public int $maxMissileDistance,
        /**
         * The part of a diagonal step that goes along each axis
         */
        public float $diagonal,
        public float $turnDistance,
        public float $launchHeight,
        /** @var int<1, max> */
        public int $pellets,
        /**
         * The part of the height of a monster at which it sees
         */
        public float $eyePart,
    ) {}

    public static function createFromRecord(DataRecord $record): self
    {
        return new self(
            $record->getFloat('missileNear'),
            $record->getFloat('noClawBonus'),
            $record->getInt('maxMissileDistance'),
            $record->getFloat('diagonal'),
            $record->getFloat('turnDistance'),
            $record->getFloat('launchHeight'),
            \max(1, $record->getInt('pellets')),
            $record->getFloat('eyePart'),
        );
    }
}
