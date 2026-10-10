<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Data;

use Serafim\Doom\Exception\DataFileException;

/**
 * An object or a list of a JSON data file with typed access to its fields.
 */
final readonly class DataRecord
{
    public function __construct(
        private string $file,
        private string $path,
        /** @var array<array-key, mixed> */
        private array $data,
    ) {}

    public function has(string $key): bool
    {
        return ($this->data[$key] ?? null) !== null;
    }

    /**
     * @return non-empty-string
     * @throws DataFileException in case of the field is missing or is not a string
     */
    public function getString(string $key): string
    {
        $value = $this->data[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            throw DataFileException::becauseFieldIsInvalid($this->file, $this->getPath($key), 'a non-empty string');
        }

        return $value;
    }

    /**
     * @return non-empty-string|null
     * @throws DataFileException in case of the field is not a string
     */
    public function findString(string $key): ?string
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->getString($key);
    }

    /**
     * @throws DataFileException in case of the field is missing or is not an integer
     */
    public function getInt(string $key): int
    {
        $value = $this->data[$key] ?? null;

        if (!\is_int($value)) {
            throw DataFileException::becauseFieldIsInvalid($this->file, $this->getPath($key), 'an integer');
        }

        return $value;
    }

    /**
     * @throws DataFileException in case of the field is not an integer
     */
    public function findInt(string $key, int $default): int
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->getInt($key);
    }

    /**
     * @throws DataFileException in case of the field is missing or is not a number
     */
    public function getFloat(string $key): float
    {
        $value = $this->data[$key] ?? null;

        if (!\is_int($value) && !\is_float($value)) {
            throw DataFileException::becauseFieldIsInvalid($this->file, $this->getPath($key), 'a number');
        }

        return (float) $value;
    }

    /**
     * @throws DataFileException in case of the field is not a number
     */
    public function findFloat(string $key, float $default): float
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->getFloat($key);
    }

    /**
     * @throws DataFileException in case of the field is not a boolean
     */
    public function findBool(string $key, bool $default = false): bool
    {
        if (!$this->has($key)) {
            return $default;
        }

        $value = $this->data[$key];

        if (!\is_bool($value)) {
            throw DataFileException::becauseFieldIsInvalid($this->file, $this->getPath($key), 'a boolean');
        }

        return $value;
    }

    /**
     * @return list<float>
     * @throws DataFileException in case of the field is missing or is not a list of numbers
     */
    public function getFloats(string $key): array
    {
        $floats = [];

        foreach ($this->getList($key, 'a list of numbers') as $index => $value) {
            if (!\is_int($value) && !\is_float($value)) {
                throw DataFileException::becauseFieldIsInvalid(
                    $this->file,
                    $this->getPath($key) . '.' . $index,
                    'a number',
                );
            }

            $floats[] = (float) $value;
        }

        return $floats;
    }

    /**
     * @return list<int>
     * @throws DataFileException in case of the field is missing or is not a list of integers
     */
    public function getInts(string $key): array
    {
        $ints = [];

        foreach ($this->getList($key, 'a list of integers') as $index => $value) {
            if (!\is_int($value)) {
                throw DataFileException::becauseFieldIsInvalid(
                    $this->file,
                    $this->getPath($key) . '.' . $index,
                    'an integer',
                );
            }

            $ints[] = $value;
        }

        return $ints;
    }

    /**
     * @throws DataFileException in case of the field is missing or is not an object or a list
     */
    public function getRecord(string $key): self
    {
        $value = $this->data[$key] ?? null;

        if (!\is_array($value)) {
            throw DataFileException::becauseFieldIsInvalid($this->file, $this->getPath($key), 'an object or a list');
        }

        return new self($this->file, $this->getPath($key), $value);
    }

    /**
     * The children of the record by their keys.
     *
     * @return array<array-key, self>
     * @throws DataFileException in case of a child is not an object or a list
     */
    public function getRecords(): array
    {
        $records = [];

        foreach (\array_keys($this->data) as $key) {
            $records[$key] = $this->getRecord((string) $key);
        }

        return $records;
    }

    /**
     * @return list<non-empty-string>
     * @throws DataFileException in case of an item is not a string
     */
    public function getStrings(): array
    {
        $strings = [];

        foreach (\array_keys($this->data) as $key) {
            $strings[] = $this->getString((string) $key);
        }

        return $strings;
    }

    /**
     * The case of an enum that the field names.
     *
     * @template E of \UnitEnum
     *
     * @param class-string<E> $enum
     *
     * @return E
     * @throws DataFileException in case of the field is missing or names no case of the enum
     */
    public function getEnum(string $key, string $enum): \UnitEnum
    {
        $name = $this->getString($key);

        foreach ($enum::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw DataFileException::becauseReferenceIsUnknown($this->file, $this->getPath($key), $name);
    }

    /**
     * @template E of \UnitEnum
     *
     * @param class-string<E> $enum
     *
     * @return E|null
     * @throws DataFileException in case of the field names no case of the enum
     */
    public function findEnum(string $key, string $enum): ?\UnitEnum
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->getEnum($key, $enum);
    }

    /**
     * The case of a backed enum that has the value of the field.
     *
     * @template E of \BackedEnum
     *
     * @param class-string<E> $enum
     *
     * @return E
     * @throws DataFileException in case of the field is missing or is the value of no case
     */
    public function getEnumByValue(string $key, string $enum): \BackedEnum
    {
        $value = $this->getString($key);

        return $enum::tryFrom($value)
            ?? throw DataFileException::becauseReferenceIsUnknown($this->file, $this->getPath($key), $value);
    }

    /**
     * @param non-empty-string $expected
     *
     * @return list<mixed>
     * @throws DataFileException in case of the field is missing or is not a list
     */
    private function getList(string $key, string $expected): array
    {
        $values = $this->data[$key] ?? null;

        if (!\is_array($values) || !\array_is_list($values)) {
            throw DataFileException::becauseFieldIsInvalid($this->file, $this->getPath($key), $expected);
        }

        return $values;
    }

    private function getPath(string $key): string
    {
        if ($this->path === '') {
            return $key;
        }

        return $this->path . '.' . $key;
    }
}
