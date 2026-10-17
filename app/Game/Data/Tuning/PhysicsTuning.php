<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data\Tuning;

use Serafim\Doom\Level\Data\DataRecord;

/**
 * The movement of the player (`p_user.c`, `p_mobj.c`): units per tic.
 */
final readonly class PhysicsTuning
{
    public function __construct(
        /**
         * Thrust of one unit of the move command
         */
        public float $thrust,
        public float $friction,
        public float $stopSpeed,
        public float $maxSpeed,
        /**
         * The longest part of a move that is checked against the walls at once
         */
        public float $maxStep,
        public float $maxBob,
        public float $gravity,
        /** @var int<1, max> */
        public int $slideAttempts,
        /** @var int<1, max> */
        public int $searchIterations,
    ) {}

    public static function createFromRecord(DataRecord $record): self
    {
        return new self(
            $record->getFloat('thrust'),
            $record->getFloat('friction'),
            $record->getFloat('stopSpeed'),
            $record->getFloat('maxSpeed'),
            $record->getFloat('maxStep'),
            $record->getFloat('maxBob'),
            $record->getFloat('gravity'),
            \max(1, $record->getInt('slideAttempts')),
            \max(1, $record->getInt('searchIterations')),
        );
    }
}
