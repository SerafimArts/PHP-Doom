<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data\Tuning;

use Serafim\Doom\Level\Data\DataRecord;

/**
 * The weapon in the hands of the player (`p_pspr.c`): the screen positions are in points of 320 x 200.
 */
final readonly class WeaponTuning
{
    public function __construct(
        /**
         * Height of the raised weapon (`WEAPONTOP`)
         */
        public float $top,
        /**
         * Height of the weapon that is put down (`WEAPONBOTTOM`)
         */
        public float $bottom,
        public float $lowerSpeed,
        public float $raiseSpeed,
        public float $bulletAimRange,
        /**
         * The angle of the search for a target is a half circle divided by it
         *
         * @var int<1, max>
         */
        public int $aimSearchDivisor,
        /** @var int<1, max> */
        public int $pellets,
        /**
         * How far above the middle of the player the bullets start
         */
        public float $muzzleHeightOffset,
    ) {}

    public static function createFromRecord(DataRecord $record): self
    {
        return new self(
            $record->getFloat('top'),
            $record->getFloat('bottom'),
            $record->getFloat('lowerSpeed'),
            $record->getFloat('raiseSpeed'),
            $record->getFloat('bulletAimRange'),
            \max(1, $record->getInt('aimSearchDivisor')),
            \max(1, $record->getInt('pellets')),
            $record->getFloat('muzzleHeightOffset'),
        );
    }
}
