<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * The lumps that follow the marker of a level, in the order of the file.
 */
enum MapLump: string
{
    case THINGS = 'THINGS';

    case LINEDEFS = 'LINEDEFS';

    case SIDEDEFS = 'SIDEDEFS';

    case VERTEXES = 'VERTEXES';

    case SEGS = 'SEGS';

    case SSECTORS = 'SSECTORS';

    case NODES = 'NODES';

    case SECTORS = 'SECTORS';

    case REJECT = 'REJECT';

    case BLOCKMAP = 'BLOCKMAP';

    /**
     * Bytes of one record, {@see null} for a lump that is not a list of records.
     *
     * @return int<1, max>|null
     */
    public function getRecordSize(): ?int
    {
        return match ($this) {
            self::THINGS => 10,
            self::LINEDEFS => 14,
            self::SIDEDEFS => 30,
            self::VERTEXES => 4,
            self::SEGS => 12,
            self::SSECTORS => 4,
            self::NODES => 28,
            self::SECTORS => 26,
            self::REJECT, self::BLOCKMAP => null,
        };
    }

    /**
     * A level cannot do without the lump, the reject table and the blockmap may be missing.
     */
    public function isRequired(): bool
    {
        return $this !== self::REJECT && $this !== self::BLOCKMAP;
    }

    /**
     * @return list<non-empty-string>
     */
    public static function getNames(): array
    {
        return \array_map(static fn(self $lump): string => $lump->value, self::cases());
    }
}
