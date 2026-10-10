<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Data;

use Serafim\Doom\Exception\DataFileException;

/**
 * Reads the JSON files of the game data (`resources/data`).
 */
final readonly class DataReader
{
    public function __construct(
        public string $directory,
    ) {}

    /**
     * The data directory of the project.
     */
    public static function createDefault(): self
    {
        return new self(\dirname(__DIR__, 3) . '/resources/data');
    }

    /**
     * @throws DataFileException in case of the file is missing or is not a JSON object or list
     */
    public function read(string $file): DataRecord
    {
        $pathname = $this->directory . '/' . $file;
        $json = @\file_get_contents($pathname);

        if ($json === false) {
            throw DataFileException::becauseFileIsNotReadable($pathname);
        }

        try {
            $data = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw DataFileException::becauseJsonIsInvalid($pathname, $e->getMessage(), $e);
        }

        if (!\is_array($data)) {
            throw DataFileException::becauseJsonIsInvalid($pathname, 'the root is not an object or a list');
        }

        return new DataRecord($file, '', $data);
    }
}
