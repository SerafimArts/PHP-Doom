<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The light of a flame that goes through the frames of a sprite: radius and strength of every frame.
 */
final readonly class FlameProfile
{
    public function __construct(
        public string $sprite,
        /**
         * Height above the foot of the thing
         */
        public float $height,
        /**
         * Radius and strength by the letter of the frame
         *
         * @var array<string, array{float, float}>
         */
        public array $frames,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $frames = [];

        foreach ($record->getRecord('frames')->getRecords() as $letter => $frame) {
            $frames[(string) $letter] = [$frame->getFloat('radius'), $frame->getFloat('strength')];
        }

        return new self($record->getString('sprite'), $record->getFloat('height'), $frames);
    }

    /**
     * @return array{float, float}|null radius and strength, {@see null} for a frame that does not shine
     */
    public function findFrame(string $sprite, string $letter): ?array
    {
        if ($sprite !== $this->sprite) {
            return null;
        }

        return $this->frames[$letter] ?? null;
    }
}
