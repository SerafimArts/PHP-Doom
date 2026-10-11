<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The sprites that the first level shows, from `sprites.json`: the letters of the frames of every sprite.
 */
final readonly class SpriteCatalog
{
    public function __construct(
        /**
         * Letters of the frames by the name of the sprite
         *
         * @var array<non-empty-string, non-empty-string>
         */
        public array $frames,
    ) {}

    /**
     * @throws DataFileException in case of a row has no sprite or frames
     */
    public static function createFromRecord(DataRecord $record): self
    {
        $frames = [];

        foreach ($record->getRecords() as $row) {
            $frames[$row->getString('sprite')] = $row->getString('frames');
        }

        return new self($frames);
    }
}
