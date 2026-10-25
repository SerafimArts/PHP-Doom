<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Local\WAD\Exception\WadException;
use Local\WAD\Image\Palette;
use Local\WAD\Wad;
use Serafim\Doom\Command\Png\PngEncoder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes PNG dumps of the graphics of a WAD to check the decoders by eye: the palette, the wall textures
 * and flats of a level, sprites and interface graphics. Every group also gets a contact sheet.
 */
#[AsCommand(
    name: 'dump-assets',
    description: 'Write PNG dumps of the palette, textures, flats, sprites and HUD graphics into var/dump',
)]
final class DumpAssetsCommand extends Command
{
    /** @var list<non-empty-string> */
    private const array GRAPHICS = [
        'POSSA1', 'POSSE1', 'SPOSA1', 'TROOA1', 'TROOE1', 'BAR1A0', 'PLAYA1',
        'PISGA0', 'PUNGA0', 'SHTGA0', 'SHTFA0', 'MEDIA0', 'STIMA0', 'BON1A0', 'ARM1A0', 'CLIPA0', 'SHELA0',
        'STBAR', 'STFST00', 'STFDEAD0', 'STTNUM0', 'STYSNUM0', 'STGNUM3', 'TITLEPIC',
    ];

    private const int SHEET_WIDTH = 1024;

    private const int SHEET_GAP = 4;

    protected function configure(): void
    {
        $root = \dirname(__DIR__, 2);

        $this->addOption(
            'wad',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to the WAD file',
            $root . '/var/wad/doom1.wad',
        );
        $this->addOption('map', null, InputOption::VALUE_REQUIRED, 'Level whose textures and flats are dumped', 'E1M1');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Directory of the dump', $root . '/var/dump');
        $this->addOption(
            'scale',
            null,
            InputOption::VALUE_REQUIRED,
            'Integer upscale factor of the single images',
            '1',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $scale = (int) self::getStringOption($input, 'scale');

        if ($scale < 1 || $scale > 16) {
            $io->error('The scale must be an integer from 1 to 16.');

            return self::FAILURE;
        }

        $directory = self::getStringOption($input, 'output');
        $files = new Filesystem();

        try {
            $wad = Wad::open(self::getStringOption($input, 'wad'));
            $map = $wad->readMap(self::getStringOption($input, 'map'));
            $palettes = $wad->readPalettes();
            $lookup = self::createLookup($palettes[0]);

            $textures = [];
            $textureNames = [];

            foreach ($map->sidedefs as $sidedef) {
                foreach ([$sidedef->upperTexture, $sidedef->lowerTexture, $sidedef->middleTexture] as $name) {
                    if ($name !== null) {
                        $textureNames[$name] = true;
                    }
                }
            }

            \ksort($textureNames);

            foreach (\array_keys($textureNames) as $name) {
                $picture = $wad->readTexture($name);
                $rgba = self::toRgba($picture->pixels, $picture->mask, $picture->width, $picture->height, $lookup, 1);
                $textures[] = [$name, $picture->width, $picture->height, $rgba];
                $files->dumpFile(
                    $directory . '/textures/' . $name . '.png',
                    self::encode(
                        $picture->width,
                        $picture->height,
                        self::toRgba(
                            $picture->pixels,
                            $picture->mask,
                            $picture->width,
                            $picture->height,
                            $lookup,
                            $scale,
                        ),
                        $scale,
                    ),
                );
            }

            $flats = [];
            $flatNames = [];

            foreach ($map->sectors as $sector) {
                $flatNames[$sector->floorTexture] = true;
                $flatNames[$sector->ceilingTexture] = true;
            }

            \ksort($flatNames);

            foreach (\array_keys($flatNames) as $name) {
                $flat = $wad->readFlat($name);
                $rgba = self::toRgba($flat->pixels, null, $flat->width, $flat->height, $lookup, 1);
                $flats[] = [$name, $flat->width, $flat->height, $rgba];
                $files->dumpFile(
                    $directory . '/flats/' . $name . '.png',
                    self::encode(
                        $flat->width,
                        $flat->height,
                        self::toRgba($flat->pixels, null, $flat->width, $flat->height, $lookup, $scale),
                        $scale,
                    ),
                );
            }

            $graphics = [];

            foreach (self::GRAPHICS as $name) {
                if (!$wad->hasLump($name)) {
                    $io->writeln(\sprintf('<comment>skip</comment>  %s (no such lump)', $name));

                    continue;
                }

                $picture = $wad->readPicture($name);

                if ($picture->width === 0 || $picture->height === 0) {
                    continue;
                }

                $rgba = self::toRgba($picture->pixels, $picture->mask, $picture->width, $picture->height, $lookup, 1);
                $graphics[] = [$name, $picture->width, $picture->height, $rgba];
                $files->dumpFile(
                    $directory . '/graphics/' . $name . '.png',
                    self::encode(
                        $picture->width,
                        $picture->height,
                        self::toRgba(
                            $picture->pixels,
                            $picture->mask,
                            $picture->width,
                            $picture->height,
                            $lookup,
                            $scale,
                        ),
                        $scale,
                    ),
                );
            }

            $files->dumpFile($directory . '/palette.png', self::encodePalette($palettes[0]));

            foreach (['textures' => $textures, 'flats' => $flats, 'graphics' => $graphics] as $group => $images) {
                [$width, $height, $rgba] = self::createSheet($images);
                $files->dumpFile($directory . '/' . $group . '-sheet.png', PngEncoder::encode($width, $height, $rgba));

                $io->writeln(\sprintf('<info>dump</info>  %d %s', \count($images), $group));
            }
        } catch (WadException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        $io->success(\sprintf('Dump of %s is written to %s', $map->name, $directory));

        return self::SUCCESS;
    }

    /**
     * Returns the four byte pixels of a palette: the colors followed by the full opacity.
     *
     * @return list<non-empty-string>
     */
    private static function createLookup(Palette $palette): array
    {
        $lookup = [];

        for ($index = 0; $index < Palette::SIZE; ++$index) {
            $lookup[] = \substr($palette->rgb, $index * 3, 3) . "\xFF";
        }

        return $lookup;
    }

    /**
     * @param list<non-empty-string> $lookup
     * @param int<1, max> $scale
     */
    private static function toRgba(
        string $pixels,
        ?string $mask,
        int $width,
        int $height,
        array $lookup,
        int $scale,
    ): string {
        $rgba = '';

        for ($y = 0; $y < $height; ++$y) {
            $row = '';

            for ($x = 0; $x < $width; ++$x) {
                $cell = $y * $width + $x;
                $pixel = "\0\0\0\0";

                if ($mask === null || $mask[$cell] !== "\0") {
                    $pixel = $lookup[\ord($pixels[$cell])];
                }

                $row .= \str_repeat($pixel, $scale);
            }

            $rgba .= \str_repeat($row, $scale);
        }

        return $rgba;
    }

    /**
     * @param int<1, max> $scale
     */
    private static function encode(int $width, int $height, string $rgba, int $scale): string
    {
        return PngEncoder::encode(\max(1, $width * $scale), \max(1, $height * $scale), $rgba);
    }

    private static function encodePalette(Palette $palette): string
    {
        $cell = 16;
        $lookup = self::createLookup($palette);
        $pixels = '';

        for ($y = 0; $y < 16; ++$y) {
            $row = '';

            for ($x = 0; $x < 16; ++$x) {
                $row .= \chr($y * 16 + $x);
            }

            $pixels .= $row;
        }

        return PngEncoder::encode(16 * $cell, 16 * $cell, self::toRgba($pixels, null, 16, 16, $lookup, $cell));
    }

    /**
     * @param list<array{string, int, int, string}> $images name, width, height and RGBA pixels
     *
     * @return array{int<1, max>, int<1, max>, string}
     */
    private static function createSheet(array $images): array
    {
        $positions = [];
        $x = self::SHEET_GAP;
        $y = self::SHEET_GAP;
        $rowHeight = 0;
        $width = self::SHEET_WIDTH;

        foreach ($images as [, $imageWidth, $imageHeight]) {
            if ($x + $imageWidth + self::SHEET_GAP > $width && $x > self::SHEET_GAP) {
                $x = self::SHEET_GAP;
                $y += $rowHeight + self::SHEET_GAP;
                $rowHeight = 0;
            }

            $positions[] = [$x, $y];
            $x += $imageWidth + self::SHEET_GAP;
            $rowHeight = \max($rowHeight, $imageHeight);
            $width = \max($width, $x);
        }

        $height = $y + $rowHeight + self::SHEET_GAP;
        $canvas = \str_repeat("\x30\x30\x30\xFF", $width * $height);

        foreach ($images as $index => [, $imageWidth, $imageHeight, $rgba]) {
            [$left, $top] = $positions[$index];

            for ($row = 0; $row < $imageHeight; ++$row) {
                $canvas = \substr_replace(
                    $canvas,
                    \substr($rgba, $row * $imageWidth * 4, $imageWidth * 4),
                    (($top + $row) * $width + $left) * 4,
                    $imageWidth * 4,
                );
            }
        }

        return [$width, $height, $canvas];
    }

    private static function getStringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        \assert(\is_string($value));

        return $value;
    }
}
