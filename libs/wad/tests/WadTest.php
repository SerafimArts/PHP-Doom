<?php

declare(strict_types=1);

namespace Local\WAD\Tests;

use Local\WAD\Exception\LumpNotFoundException;
use Local\WAD\Exception\MalformedLumpException;
use Local\WAD\Exception\MalformedWadException;
use Local\WAD\Exception\WadFileException;
use Local\WAD\Tests\Support\WadBuilder;
use Local\WAD\Wad;
use Local\WAD\WadKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Wad::class)]
final class WadTest extends TestCase
{
    public function testOpeningMissingFileThrowsWadFileException(): void
    {
        $this->expectException(WadFileException::class);

        Wad::open(__DIR__ . '/missing.wad');
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function invalidWadDataProvider(): iterable
    {
        yield 'empty data' => [''];
        yield 'unknown signature' => ['ZWAD' . \pack('VV', 0, 12)];
        yield 'directory out of file' => ['IWAD' . \pack('VV', 5, 12)];
        yield 'lump out of file' => [
            'IWAD' . \pack('VV', 1, 12) . \pack('VV', 12, 99) . \str_pad('BIG', 8, "\0"),
        ];
    }

    #[DataProvider('invalidWadDataProvider')]
    public function testReadingInvalidDataThrowsMalformedWadException(string $data): void
    {
        $this->expectException(MalformedWadException::class);

        Wad::createFromString($data);
    }

    public function testSignatureTellsTheKindOfWad(): void
    {
        $iwad = Wad::createFromString(WadBuilder::build([]));
        $pwad = Wad::createFromString(WadBuilder::build([], 'PWAD'));

        self::assertSame(WadKind::IWAD, $iwad->kind);
        self::assertSame(WadKind::PWAD, $pwad->kind);
    }

    public function testLastLumpWithTheSameNameWins(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([['DATA', 'first'], ['OTHER', 'x'], ['DATA', 'second']]));

        self::assertSame('second', $wad->readLump('DATA'));
        self::assertSame(2, $wad->getLump('DATA')->index);
    }

    public function testLumpNamesAreCaseInsensitive(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([['Data', 'bytes']]));

        self::assertTrue($wad->hasLump('dATA'));
        self::assertSame('DATA', $wad->getLump('data')->name);
    }

    public function testLumpNameStopsAtTheFirstNulByte(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([["AB\0CD", 'x']]));

        self::assertSame('AB', $wad->lumps[0]->name);
    }

    public function testGettingMissingLumpThrowsLumpNotFoundException(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([]));

        self::assertNull($wad->findLump('NOPE'));

        $this->expectException(LumpNotFoundException::class);
        $wad->getLump('NOPE');
    }

    public function testRangeReturnsLumpsBetweenMarkersWithoutNestedMarkers(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([
            ['F_START', ''],
            ['F1_START', ''],
            ['FLATA', '1'],
            ['FLATB', '2'],
            ['F1_END', ''],
            ['F_END', ''],
            ['AFTER', '3'],
        ]));

        self::assertSame(['FLATA', 'FLATB'], $wad->listFlats());
    }

    public function testRangeWithoutEndMarkerThrowsLumpNotFoundException(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([['S_START', ''], ['SPRITE', 'x']]));

        $this->expectException(LumpNotFoundException::class);
        $wad->listSprites();
    }

    public function testMapsAreLevelMarkersFollowedByThings(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([
            ['PLAYPAL', ''],
            ['E1M1', ''],
            ['THINGS', ''],
            ['E1M2', ''],
            ['THINGS', ''],
        ]));

        self::assertSame(['E1M1', 'E1M2'], $wad->listMaps());
    }

    public function testFlatSizeMustBeWholeRows(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([['GOOD', \str_repeat("\x05", 4096)], ['BAD', 'abc']]));

        self::assertSame(64, $wad->readFlat('good')->height);
        self::assertSame(4096, \strlen($wad->readFlat('GOOD')->pixels));

        $this->expectException(MalformedLumpException::class);
        $wad->readFlat('BAD');
    }

    public function testPalettesAndColormapAreSplitIntoTables(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([
            ['PLAYPAL', \str_repeat("\x01\x02\x03", 256) . \str_repeat("\x04\x05\x06", 256)],
            ['COLORMAP', \str_repeat("\x07", 256) . \str_repeat("\x08", 256) . \str_repeat("\x09", 256)],
        ]));

        $palettes = $wad->readPalettes();

        self::assertCount(2, $palettes);
        self::assertSame(['red' => 4, 'green' => 5, 'blue' => 6], $palettes[1]->getColor(255));
        self::assertCount(3, $wad->readColormap()->rows);
    }

    public function testPaletteWithBrokenSizeThrowsMalformedLumpException(): void
    {
        $wad = Wad::createFromString(WadBuilder::build([['PLAYPAL', \str_repeat('x', 100)]]));

        $this->expectException(MalformedLumpException::class);
        $wad->readPalettes();
    }

    public function testOpeningAFileReadsItsDirectory(): void
    {
        $pathname = \tempnam(\sys_get_temp_dir(), 'wad');
        self::assertNotFalse($pathname);

        try {
            \file_put_contents($pathname, WadBuilder::build([['HELLO', 'world']]));

            $wad = Wad::open($pathname);

            self::assertSame($pathname, $wad->source);
            self::assertSame('world', $wad->readLump('hello'));
        } finally {
            \unlink($pathname);
        }
    }
}
