<?php

declare(strict_types=1);

namespace Local\WAD\Map;

use Local\WAD\Binary;
use Local\WAD\Exception\LumpNotFoundException;
use Local\WAD\Exception\MalformedLumpException;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\WAD
 */
final readonly class MapDecoder
{
    private const int NO_SIDEDEF = 0xFFFF;

    private const int SUBSECTOR_FLAG = 0x8000;

    private const int INDEX_MASK = 0x7FFF;

    private const int END_OF_BLOCK = 0xFFFF;

    private function __construct() {}

    /**
     * @param array<non-empty-string, string> $lumps the data of the lumps of the level by their names, REJECT and
     *        BLOCKMAP may be missing
     *
     * @throws LumpNotFoundException in case of a lump other than REJECT and BLOCKMAP is missing
     * @throws MalformedLumpException in case of a lump is not valid
     */
    public static function decode(string $name, array $lumps): MapData
    {
        foreach (MapLump::cases() as $lump) {
            if ($lump->isRequired() && !isset($lumps[$lump->value])) {
                throw LumpNotFoundException::becauseMapLumpDoesNotExist($name, $lump->value);
            }
        }

        $blockmap = null;

        if (isset($lumps['BLOCKMAP'])) {
            $blockmap = self::decodeBlockmap($lumps['BLOCKMAP']);
        }

        return new MapData(
            name: $name,
            things: self::decodeThings($lumps['THINGS']),
            linedefs: self::decodeLinedefs($lumps['LINEDEFS']),
            sidedefs: self::decodeSidedefs($lumps['SIDEDEFS']),
            vertexes: self::decodeVertexes($lumps['VERTEXES']),
            segs: self::decodeSegs($lumps['SEGS']),
            subsectors: self::decodeSubsectors($lumps['SSECTORS']),
            nodes: self::decodeNodes($lumps['NODES']),
            sectors: self::decodeSectors($lumps['SECTORS']),
            reject: $lumps['REJECT'] ?? '',
            blockmap: $blockmap,
        );
    }

    /**
     * @return list<Thing>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeThings(string $data): array
    {
        $count = self::countRecords(MapLump::THINGS, $data);
        $things = [];

        for ($i = 0; $i < $count; ++$i) {
            $o = $i * 10;

            $things[] = new Thing(
                x: Binary::int16($data, $o),
                y: Binary::int16($data, $o + 2),
                angle: Binary::uint16($data, $o + 4),
                type: Binary::uint16($data, $o + 6),
                flags: Binary::uint16($data, $o + 8),
            );
        }

        return $things;
    }

    /**
     * @return list<Linedef>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeLinedefs(string $data): array
    {
        $count = self::countRecords(MapLump::LINEDEFS, $data);
        $linedefs = [];

        for ($i = 0; $i < $count; ++$i) {
            $o = $i * 14;
            $left = Binary::uint16($data, $o + 12);

            $leftSidedef = null;

            if ($left !== self::NO_SIDEDEF) {
                $leftSidedef = $left;
            }

            $linedefs[] = new Linedef(
                startVertex: Binary::uint16($data, $o),
                endVertex: Binary::uint16($data, $o + 2),
                flags: Binary::uint16($data, $o + 4),
                special: Binary::uint16($data, $o + 6),
                tag: Binary::uint16($data, $o + 8),
                rightSidedef: Binary::uint16($data, $o + 10),
                leftSidedef: $leftSidedef,
            );
        }

        return $linedefs;
    }

    /**
     * @return list<Sidedef>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeSidedefs(string $data): array
    {
        $count = self::countRecords(MapLump::SIDEDEFS, $data);
        $sidedefs = [];

        for ($i = 0; $i < $count; ++$i) {
            $o = $i * 30;

            $sidedefs[] = new Sidedef(
                xOffset: Binary::int16($data, $o),
                yOffset: Binary::int16($data, $o + 2),
                upperTexture: self::decodeTextureName($data, $o + 4),
                lowerTexture: self::decodeTextureName($data, $o + 12),
                middleTexture: self::decodeTextureName($data, $o + 20),
                sector: Binary::uint16($data, $o + 28),
            );
        }

        return $sidedefs;
    }

    /**
     * @return list<Vertex>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeVertexes(string $data): array
    {
        $count = self::countRecords(MapLump::VERTEXES, $data);
        $vertexes = [];

        for ($i = 0; $i < $count; ++$i) {
            $vertexes[] = new Vertex(
                x: Binary::int16($data, $i * 4),
                y: Binary::int16($data, $i * 4 + 2),
            );
        }

        return $vertexes;
    }

    /**
     * @return list<Seg>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeSegs(string $data): array
    {
        $count = self::countRecords(MapLump::SEGS, $data);
        $segs = [];

        for ($i = 0; $i < $count; ++$i) {
            $o = $i * 12;

            $segs[] = new Seg(
                startVertex: Binary::uint16($data, $o),
                endVertex: Binary::uint16($data, $o + 2),
                angle: Binary::uint16($data, $o + 4),
                linedef: Binary::uint16($data, $o + 6),
                isOpposite: Binary::uint16($data, $o + 8) !== 0,
                offset: Binary::int16($data, $o + 10),
            );
        }

        return $segs;
    }

    /**
     * @return list<Subsector>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeSubsectors(string $data): array
    {
        $count = self::countRecords(MapLump::SSECTORS, $data);
        $subsectors = [];

        for ($i = 0; $i < $count; ++$i) {
            $subsectors[] = new Subsector(
                segCount: Binary::uint16($data, $i * 4),
                firstSeg: Binary::uint16($data, $i * 4 + 2),
            );
        }

        return $subsectors;
    }

    /**
     * @return list<Node>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeNodes(string $data): array
    {
        $count = self::countRecords(MapLump::NODES, $data);
        $nodes = [];

        for ($i = 0; $i < $count; ++$i) {
            $o = $i * 28;

            $nodes[] = new Node(
                x: Binary::int16($data, $o),
                y: Binary::int16($data, $o + 2),
                dx: Binary::int16($data, $o + 4),
                dy: Binary::int16($data, $o + 6),
                rightBox: self::decodeBoundingBox($data, $o + 8),
                leftBox: self::decodeBoundingBox($data, $o + 16),
                rightChild: self::decodeNodeChild(Binary::uint16($data, $o + 24)),
                leftChild: self::decodeNodeChild(Binary::uint16($data, $o + 26)),
            );
        }

        return $nodes;
    }

    /**
     * @return list<Sector>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    public static function decodeSectors(string $data): array
    {
        $count = self::countRecords(MapLump::SECTORS, $data);
        $sectors = [];

        for ($i = 0; $i < $count; ++$i) {
            $o = $i * 26;

            $sectors[] = new Sector(
                floorHeight: Binary::int16($data, $o),
                ceilingHeight: Binary::int16($data, $o + 2),
                floorTexture: Binary::name($data, $o + 4),
                ceilingTexture: Binary::name($data, $o + 12),
                lightLevel: Binary::uint16($data, $o + 20),
                special: Binary::uint16($data, $o + 22),
                tag: Binary::uint16($data, $o + 24),
            );
        }

        return $sectors;
    }

    /**
     * @throws MalformedLumpException in case of the offsets or the lists point out of the lump
     */
    public static function decodeBlockmap(string $data): Blockmap
    {
        $size = \strlen($data);

        if ($size < 8) {
            throw MalformedLumpException::becauseDataIsTruncated('BLOCKMAP');
        }

        $columns = Binary::uint16($data, 4);
        $rows = Binary::uint16($data, 6);
        $count = $columns * $rows;

        if ($size < 8 + $count * 2) {
            throw MalformedLumpException::becauseDataIsTruncated('BLOCKMAP');
        }

        $blocks = [];

        for ($block = 0; $block < $count; ++$block) {
            $position = Binary::uint16($data, 8 + $block * 2) * 2;

            if ($position + 2 > $size) {
                throw MalformedLumpException::becauseReferenceIsOutOfRange('BLOCKMAP', 'block list offset', $position);
            }

            // The first value of every list is a start marker, the lines follow it.
            $position += 2;
            $lines = [];

            while (true) {
                if ($position + 2 > $size) {
                    throw MalformedLumpException::becauseDataIsTruncated('BLOCKMAP');
                }

                $line = Binary::uint16($data, $position);

                if ($line === self::END_OF_BLOCK) {
                    break;
                }

                $lines[] = $line;
                $position += 2;
            }

            $blocks[] = $lines;
        }

        return new Blockmap(
            originX: Binary::int16($data, 0),
            originY: Binary::int16($data, 2),
            columns: $columns,
            rows: $rows,
            blocks: $blocks,
        );
    }

    /**
     * @return int<0, max>
     * @throws MalformedLumpException in case of the size is not a multiple of the record
     */
    private static function countRecords(MapLump $lump, string $data): int
    {
        $recordSize = $lump->getRecordSize() ?? throw new \LogicException($lump->value . ' is not a list of records');
        $size = \strlen($data);

        if ($size % $recordSize !== 0) {
            throw MalformedLumpException::becauseSizeIsNotMultipleOfRecord($lump->value, $size, $recordSize);
        }

        $count = \intdiv($size, $recordSize);
        \assert($count >= 0);

        return $count;
    }

    private static function decodeTextureName(string $data, int $offset): ?string
    {
        $name = Binary::name($data, $offset);

        if ($name === '-' || $name === '') {
            return null;
        }

        return $name;
    }

    private static function decodeBoundingBox(string $data, int $offset): BoundingBox
    {
        return new BoundingBox(
            top: Binary::int16($data, $offset),
            bottom: Binary::int16($data, $offset + 2),
            left: Binary::int16($data, $offset + 4),
            right: Binary::int16($data, $offset + 6),
        );
    }

    /**
     * @param int<0, 65535> $value
     */
    private static function decodeNodeChild(int $value): NodeChild
    {
        if (($value & self::SUBSECTOR_FLAG) !== 0) {
            return new NodeChild(true, $value & self::INDEX_MASK);
        }

        return new NodeChild(false, $value & self::INDEX_MASK);
    }
}
