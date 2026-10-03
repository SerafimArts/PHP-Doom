<?php

declare(strict_types=1);

namespace Local\WAD\Texture;

use Local\WAD\Binary;
use Local\WAD\Exception\MalformedLumpException;

/**
 * Reads the lists of wall textures: PNAMES and TEXTURE1 / TEXTURE2.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\WAD
 */
final readonly class TextureDecoder
{
    private const int NAME_SIZE = 8;

    private const int DEFINITION_HEADER_SIZE = 22;

    private const int PATCH_RECORD_SIZE = 10;

    private function __construct() {}

    /**
     * Returns the names of the patches that texture definitions refer to by index.
     *
     * @return list<string>
     * @throws MalformedLumpException in case of the data is truncated
     */
    public static function decodePatchNames(string $lump, string $data): array
    {
        if (\strlen($data) < 4) {
            throw MalformedLumpException::becauseDataIsTruncated($lump);
        }

        $count = Binary::uint32($data, 0);

        if (\strlen($data) < 4 + $count * self::NAME_SIZE) {
            throw MalformedLumpException::becauseDataIsTruncated($lump);
        }

        $names = [];

        for ($i = 0; $i < $count; ++$i) {
            $names[] = Binary::name($data, 4 + $i * self::NAME_SIZE);
        }

        return $names;
    }

    /**
     * @param list<string> $patchNames the names from PNAMES
     *
     * @return list<TextureDefinition>
     * @throws MalformedLumpException in case of the data is truncated or refers to an unknown patch
     */
    public static function decodeDefinitions(string $lump, string $data, array $patchNames): array
    {
        $size = \strlen($data);

        if ($size < 4) {
            throw MalformedLumpException::becauseDataIsTruncated($lump);
        }

        $count = Binary::uint32($data, 0);

        if ($size < 4 + $count * 4) {
            throw MalformedLumpException::becauseDataIsTruncated($lump);
        }

        $definitions = [];

        for ($i = 0; $i < $count; ++$i) {
            $offset = Binary::uint32($data, 4 + $i * 4);

            if ($offset + self::DEFINITION_HEADER_SIZE > $size) {
                throw MalformedLumpException::becauseReferenceIsOutOfRange($lump, 'texture offset', $offset);
            }

            $patchCount = Binary::uint16($data, $offset + 20);
            $end = $offset + self::DEFINITION_HEADER_SIZE + $patchCount * self::PATCH_RECORD_SIZE;

            if ($end > $size) {
                throw MalformedLumpException::becauseDataIsTruncated($lump);
            }

            $patches = [];

            for ($p = 0; $p < $patchCount; ++$p) {
                $record = $offset + self::DEFINITION_HEADER_SIZE + $p * self::PATCH_RECORD_SIZE;
                $index = Binary::uint16($data, $record + 4);

                if (!isset($patchNames[$index])) {
                    throw MalformedLumpException::becauseReferenceIsOutOfRange($lump, 'patch index', $index);
                }

                $patches[] = new TexturePatch(
                    patchName: $patchNames[$index],
                    originX: Binary::int16($data, $record),
                    originY: Binary::int16($data, $record + 2),
                );
            }

            $definitions[] = new TextureDefinition(
                name: Binary::name($data, $offset),
                width: Binary::uint16($data, $offset + 12),
                height: Binary::uint16($data, $offset + 14),
                patches: $patches,
            );
        }

        return $definitions;
    }
}
