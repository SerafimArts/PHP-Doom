<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * Which face of the player the status bar shows (`ST_updateFaceWidget`), from `hud/face.json`.
 */
final readonly class FaceRules
{
    public function __construct(
        public string $deadPicture,
        public string $ouchPrefix,
        public string $lookPrefix,
        /**
         * Damage taken since the last tic that makes the face wince
         */
        public int $ouchDamage,
        /** @var int<1, max> */
        public int $painLevels,
        /** @var int<1, max> */
        public int $painDivisor,
        public int $maxHealth,
        /** @var int<1, max> */
        public int $lookTics,
        /**
         * Where the face looks, by the step of the idle animation
         *
         * @var non-empty-list<int>
         */
        public array $look,
    ) {}

    /**
     * @throws DataFileException in case of a field is missing or is not valid
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $look = $record->getInts('look');
        $painLevels = $record->getInt('painLevels');
        $painDivisor = $record->getInt('painDivisor');
        $lookTics = $record->getInt('lookTics');

        if ($look === []) {
            throw DataFileException::becauseFieldIsInvalid('hud/face.json', 'look', 'a non-empty list');
        }

        if ($painLevels < 1 || $painDivisor < 1 || $lookTics < 1) {
            throw DataFileException::becauseFieldIsInvalid('hud/face.json', 'painLevels', 'positive steps');
        }

        return new self(
            $record->getString('deadPicture'),
            $record->getString('ouchPrefix'),
            $record->getString('lookPrefix'),
            $record->getInt('ouchDamage'),
            $painLevels,
            $painDivisor,
            $record->getInt('maxHealth'),
            $lookTics,
            $look,
        );
    }
}
