<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The lumps of the status bar, the numbers, the faces, the letters of the messages and the intermission screen, from
 * `hud/graphics.json`.
 */
final readonly class GraphicsCatalog
{
    public function __construct(
        /** @var list<non-empty-string> */
        public array $names,
    ) {}

    /**
     * @throws DataFileException in case of the names are missing
     */
    public static function createFromRecord(DataRecord $record): self
    {
        return new self($record->getRecord('names')->getStrings());
    }
}
