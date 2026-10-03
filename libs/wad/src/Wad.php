<?php

declare(strict_types=1);

namespace Local\WAD;

use Local\WAD\Exception\LumpNotFoundException;
use Local\WAD\Exception\MalformedLumpException;
use Local\WAD\Exception\MalformedWadException;
use Local\WAD\Exception\WadFileException;
use Local\WAD\Image\Colormap;
use Local\WAD\Image\Flat;
use Local\WAD\Image\Palette;
use Local\WAD\Image\Picture;
use Local\WAD\Image\PictureDecoder;
use Local\WAD\Map\MapData;
use Local\WAD\Map\MapDecoder;
use Local\WAD\Map\MapLump;
use Local\WAD\Texture\TextureComposer;
use Local\WAD\Texture\TextureDecoder;
use Local\WAD\Texture\TextureDefinition;
use Local\WAD\Wad\LumpName;
use Local\WAD\Wad\MarkerRange;

/**
 * A WAD file: the directory of lumps and the readers of the lumps the game is made of.
 * Lump names are case insensitive and the last lump with a name wins.
 */
final class Wad
{
    private const int HEADER_SIZE = 12;

    private const int DIRECTORY_ENTRY_SIZE = 16;

    private const int FLAT_WIDTH = 64;

    /** @var array<string, int<0, max>> */
    private array $index = [];

    /** @var array<string, TextureDefinition>|null */
    private ?array $textureDefinitions = null;

    /** @var array<string, Picture> */
    private array $patches = [];

    private function __construct(
        public readonly WadKind $kind,
        /**
         * File path or a label of the in-memory data
         */
        public readonly string $source,
        /** @var list<Lump> */
        public readonly array $lumps,
        private readonly string $data,
    ) {
        foreach ($lumps as $lump) {
            $this->index[$lump->name] = $lump->index;
        }
    }

    /**
     * @api
     *
     * @throws WadFileException in case of the file cannot be read
     * @throws MalformedWadException in case of the file is not a valid WAD
     */
    public static function open(string $pathname): self
    {
        if (!\is_file($pathname) || !\is_readable($pathname)) {
            throw WadFileException::becauseFileIsNotReadable($pathname);
        }

        $data = \file_get_contents($pathname);

        if ($data === false) {
            throw WadFileException::becauseFileIsNotReadable($pathname);
        }

        return self::createFromString($data, $pathname);
    }

    /**
     * @api
     *
     * @param string $source a label for messages, for example the path
     *
     * @throws MalformedWadException in case of the data is not a valid WAD
     */
    public static function createFromString(string $data, string $source = 'memory'): self
    {
        $size = \strlen($data);

        if ($size < self::HEADER_SIZE) {
            throw MalformedWadException::becauseHeaderIsInvalid($source);
        }

        $kind = WadKind::tryFrom(\substr($data, 0, 4));

        if ($kind === null) {
            throw MalformedWadException::becauseHeaderIsInvalid($source);
        }

        $count = Binary::uint32($data, 4);
        $directory = Binary::uint32($data, 8);

        if ($directory + $count * self::DIRECTORY_ENTRY_SIZE > $size) {
            throw MalformedWadException::becauseDirectoryIsOutOfFile($source);
        }

        $lumps = [];

        for ($i = 0; $i < $count; ++$i) {
            $entry = $directory + $i * self::DIRECTORY_ENTRY_SIZE;
            $lump = new Lump(
                index: $i,
                name: Binary::name($data, $entry + 8),
                offset: Binary::uint32($data, $entry),
                size: Binary::uint32($data, $entry + 4),
            );

            if ($lump->size > 0 && $lump->offset + $lump->size > $size) {
                throw MalformedWadException::becauseLumpIsOutOfFile($source, $lump->name);
            }

            $lumps[] = $lump;
        }

        return new self($kind, $source, $lumps, $data);
    }

    /**
     * @api
     */
    public function hasLump(string $name): bool
    {
        return $this->findLump($name) !== null;
    }

    /**
     * @api
     */
    public function findLump(string $name): ?Lump
    {
        $index = $this->index[\strtoupper($name)] ?? null;

        if ($index === null) {
            return null;
        }

        return $this->lumps[$index];
    }

    /**
     * @api
     *
     * @throws LumpNotFoundException in case of there is no lump with the name
     */
    public function getLump(string $name): Lump
    {
        return $this->findLump($name)
            ?? throw LumpNotFoundException::becauseLumpDoesNotExist($name);
    }

    /**
     * @api
     *
     * @throws LumpNotFoundException in case of the name is unknown
     */
    public function readLump(Lump|string $lump): string
    {
        if (\is_string($lump)) {
            $lump = $this->getLump($lump);
        }

        return \substr($this->data, $lump->offset, $lump->size);
    }

    /**
     * Returns the lumps between two marker lumps, nested markers (`F1_START`) are skipped.
     *
     * @api
     *
     * @return list<Lump>
     * @throws LumpNotFoundException in case of a marker does not exist
     */
    public function getRange(string $start, string $end): array
    {
        $startLump = $this->getMarker($start);
        $end = \strtoupper($end);
        $lumps = [];

        for ($i = $startLump->index + 1, $total = \count($this->lumps); $i < $total; ++$i) {
            $lump = $this->lumps[$i];

            if ($lump->name === $end) {
                return $lumps;
            }

            if ($lump->size === 0 && self::isMarkerName($lump->name)) {
                continue;
            }

            $lumps[] = $lump;
        }

        throw LumpNotFoundException::becauseMarkerDoesNotExist($end);
    }

    /**
     * @api
     *
     * @return list<string>
     * @throws LumpNotFoundException in case of the WAD has no sprite markers
     */
    public function listSprites(): array
    {
        return $this->listMarked(MarkerRange::SPRITES);
    }

    /**
     * @api
     *
     * @return list<string>
     * @throws LumpNotFoundException in case of the WAD has no flat markers
     */
    public function listFlats(): array
    {
        return $this->listMarked(MarkerRange::FLATS);
    }

    /**
     * @api
     *
     * @return list<string>
     */
    public function listMaps(): array
    {
        $maps = [];

        foreach ($this->lumps as $lump) {
            $next = $this->lumps[$lump->index + 1] ?? null;

            if ($next !== null && $next->name === MapLump::THINGS->value) {
                $maps[] = $lump->name;
            }
        }

        return $maps;
    }

    /**
     * Returns the 14 palettes of the screen: the normal one and the tints of damage, pickups and radiation suit.
     *
     * @api
     *
     * @return list<Palette>
     * @throws LumpNotFoundException in case of PLAYPAL does not exist
     * @throws MalformedLumpException in case of PLAYPAL is not a multiple of the palette size
     */
    public function readPalettes(): array
    {
        $data = $this->readLump(LumpName::PLAYPAL->value);
        $size = Palette::SIZE * 3;

        if ($data === '' || \strlen($data) % $size !== 0) {
            throw MalformedLumpException::becauseSizeIsNotMultipleOfRecord(
                LumpName::PLAYPAL->value,
                \strlen($data),
                $size,
            );
        }

        $palettes = [];

        foreach (\str_split($data, $size) as $rgb) {
            $palettes[] = new Palette($rgb);
        }

        return $palettes;
    }

    /**
     * @api
     *
     * @throws LumpNotFoundException in case of COLORMAP does not exist
     * @throws MalformedLumpException in case of COLORMAP is not a multiple of 256 bytes
     */
    public function readColormap(): Colormap
    {
        $data = $this->readLump(LumpName::COLORMAP->value);

        if ($data === '' || \strlen($data) % Palette::SIZE !== 0) {
            throw MalformedLumpException::becauseSizeIsNotMultipleOfRecord(
                LumpName::COLORMAP->value,
                \strlen($data),
                Palette::SIZE,
            );
        }

        return new Colormap(\str_split($data, Palette::SIZE));
    }

    /**
     * Decodes a patch: a wall piece, a sprite or an interface graphic.
     *
     * @api
     *
     * @throws LumpNotFoundException in case of the lump does not exist
     * @throws MalformedLumpException in case of the lump is not a valid patch
     */
    public function readPicture(string $name): Picture
    {
        $lump = $this->getLump($name);

        return PictureDecoder::decode($lump->name, $this->readLump($lump));
    }

    /**
     * @api
     *
     * @throws LumpNotFoundException in case of the lump does not exist
     * @throws MalformedLumpException in case of the size of the lump is not a whole number of rows
     */
    public function readFlat(string $name): Flat
    {
        $lump = $this->getLump($name);
        $size = $lump->size;

        if ($size === 0 || $size % self::FLAT_WIDTH !== 0) {
            throw MalformedLumpException::becauseSizeIsNotMultipleOfRecord($lump->name, $size, self::FLAT_WIDTH);
        }

        $height = \intdiv($size, self::FLAT_WIDTH);
        \assert($height >= 0);

        return new Flat(
            name: $lump->name,
            width: self::FLAT_WIDTH,
            height: $height,
            pixels: $this->readLump($lump),
        );
    }

    /**
     * Returns the definitions of the wall textures from TEXTURE1 and TEXTURE2 by name. A later definition
     * replaces an earlier one with the same name.
     *
     * @api
     *
     * @return array<string, TextureDefinition>
     * @throws LumpNotFoundException in case of PNAMES or TEXTURE1 does not exist
     * @throws MalformedLumpException in case of the lumps are not valid
     */
    public function readTextureDefinitions(): array
    {
        if ($this->textureDefinitions !== null) {
            return $this->textureDefinitions;
        }

        $patchNames = TextureDecoder::decodePatchNames(
            LumpName::PNAMES->value,
            $this->readLump(LumpName::PNAMES->value),
        );
        $definitions = [];

        $tables = [LumpName::TEXTURE1->value => $this->getLump(LumpName::TEXTURE1->value)];
        $second = $this->findLump(LumpName::TEXTURE2->value);

        if ($second !== null) {
            $tables[LumpName::TEXTURE2->value] = $second;
        }

        foreach ($tables as $name => $lump) {
            foreach (TextureDecoder::decodeDefinitions($name, $this->readLump($lump), $patchNames) as $definition) {
                $definitions[$definition->name] = $definition;
            }
        }

        return $this->textureDefinitions = $definitions;
    }

    /**
     * Draws a wall texture from its patches. The result keeps the transparency of the patches.
     *
     * @api
     *
     * @throws LumpNotFoundException in case of the texture or one of its patches does not exist
     * @throws MalformedLumpException in case of the lumps are not valid
     */
    public function readTexture(string $name): Picture
    {
        $name = \strtoupper($name);
        $definition = $this->readTextureDefinitions()[$name]
            ?? throw LumpNotFoundException::becauseTextureDoesNotExist($name);

        return TextureComposer::compose($definition, $this->readPatch(...));
    }

    /**
     * @api
     *
     * @throws LumpNotFoundException in case of the level or one of its required lumps does not exist
     * @throws MalformedLumpException in case of a lump is not valid
     */
    public function readMap(string $name): MapData
    {
        $marker = $this->getLump($name);
        $lumps = [];

        for ($i = $marker->index + 1, $total = \count($this->lumps); $i < $total; ++$i) {
            $lump = $this->lumps[$i];

            if (!\in_array($lump->name, MapLump::getNames(), true)) {
                break;
            }

            $lumps[$lump->name] = $this->readLump($lump);
        }

        return MapDecoder::decode($marker->name, $lumps);
    }

    /**
     * @throws LumpNotFoundException in case of the marker does not exist
     */
    private function getMarker(string $name): Lump
    {
        return $this->findLump($name)
            ?? throw LumpNotFoundException::becauseMarkerDoesNotExist($name);
    }

    /**
     * @return list<string>
     * @throws LumpNotFoundException in case of the WAD has no markers of the range
     */
    private function listMarked(MarkerRange $range): array
    {
        [$start, $end] = $range->getMarkers();

        if (!$this->hasLump($start)) {
            [$start, $end] = $range->getPwadMarkers();
        }

        return $this->listNames($start, $end);
    }

    /**
     * @return list<string>
     * @throws LumpNotFoundException in case of a marker does not exist
     */
    private function listNames(string $start, string $end): array
    {
        $names = [];

        foreach ($this->getRange($start, $end) as $lump) {
            $names[] = $lump->name;
        }

        return $names;
    }

    private static function isMarkerName(string $name): bool
    {
        return \str_ends_with($name, '_START') || \str_ends_with($name, '_END');
    }

    /**
     * @throws LumpNotFoundException in case of the lump does not exist
     * @throws MalformedLumpException in case of the lump is not a valid patch
     */
    private function readPatch(string $name): Picture
    {
        return $this->patches[$name] ??= $this->readPicture($name);
    }
}
