<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Local\WAD\Exception\WadException;
use Local\WAD\Map\MapData;
use Local\WAD\Wad;
use Serafim\Doom\Command\DumpLevel\MapCanvas;
use Serafim\Doom\Command\Png\PngEncoder;
use Serafim\Doom\Level\Level;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Draws the plan of a level to check its geometry by eye: the floor polygons of the subsectors colored by their
 * sector, the linedefs and the things.
 */
#[AsCommand(
    name: 'dump-level',
    description: 'Write a PNG plan of a level (sector polygons, linedefs, things) into var/dump',
)]
final class DumpLevelCommand extends Command
{
    private const int MARGIN = 16;

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
        $this->addOption('map', null, InputOption::VALUE_REQUIRED, 'Level to draw', 'E1M1');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'PNG file', $root . '/var/dump/level.png');
        $this->addOption('scale', null, InputOption::VALUE_REQUIRED, 'Pixels per 10 map units', '4');
    }

    /**
     * @throws WadException in case of the WAD or the level cannot be read
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $started = \microtime(true);
        $map = Wad::open($this->getOption($input, 'wad'))->readMap($this->getOption($input, 'map'));
        $level = Level::createFromMap($map);
        $built = \microtime(true) - $started;

        $scale = \max(0.5, (float) $this->getOption($input, 'scale')) / 10;
        $bounds = $level->bounds;
        $minX = $bounds->minX;
        $minY = $bounds->minY;
        $maxX = $bounds->maxX;
        $maxY = $bounds->maxY;

        $width = (int) \ceil(($maxX - $minX) * $scale) + self::MARGIN * 2;
        $height = (int) \ceil(($maxY - $minY) * $scale) + self::MARGIN * 2;
        \assert($width > 0 && $height > 0);

        $canvas = new MapCanvas($width, $height);

        $toImage = static fn(float $x, float $y): array => [
            ($x - $minX) * $scale + self::MARGIN,
            ($maxY - $y) * $scale + self::MARGIN,
        ];

        foreach ($level->geometry->polygons as $polygon) {
            $points = [];

            foreach ($polygon->points as $point) {
                $points[] = $toImage($point->x, $point->y);
            }

            $canvas->fillPolygon($points, $this->getSectorColor($map, $polygon->sector));
        }

        $this->drawLinedefs($canvas, $map, $toImage);
        $this->drawThings($canvas, $map, $toImage);

        $pathname = $this->getOption($input, 'output');
        new Filesystem()->dumpFile($pathname, PngEncoder::encode($width, $height, $canvas->toRgba()));

        $io->definitionList(
            ['Level' => \sprintf(
                '%s: %d linedefs, %d sectors, %d subsectors',
                $map->name,
                \count($map->linedefs),
                \count($map->sectors),
                \count($map->subsectors),
            )],
            ['Geometry' => \sprintf(
                '%d polygons, %d wall parts, built in %.0f ms',
                \count($level->geometry->polygons),
                \count($level->geometry->walls),
                $built * 1000,
            )],
            ['Image' => \sprintf('%s (%dx%d)', $pathname, $width, $height)],
        );

        return self::SUCCESS;
    }

    /**
     * @param \Closure(float, float): array{float, float} $toImage
     */
    private function drawLinedefs(MapCanvas $canvas, MapData $map, \Closure $toImage): void
    {
        foreach ($map->linedefs as $linedef) {
            $from = $map->vertexes[$linedef->startVertex];
            $to = $map->vertexes[$linedef->endVertex];

            $color = 0xFFFFFF;

            if ($linedef->leftSidedef !== null) {
                $right = $map->sectors[$map->sidedefs[$linedef->rightSidedef]->sector];
                $left = $map->sectors[$map->sidedefs[$linedef->leftSidedef]->sector];

                $color = 0x707070;

                if ($right->floorHeight !== $left->floorHeight) {
                    $color = 0xC0A060;
                }

                if ($right->ceilingHeight !== $left->ceilingHeight) {
                    $color = 0xA0C060;
                }
            }

            if ($linedef->special !== 0) {
                $color = 0xFF3030;
            }

            [$x1, $y1] = $toImage($from->x, $from->y);
            [$x2, $y2] = $toImage($to->x, $to->y);

            $canvas->drawLine($x1, $y1, $x2, $y2, $color);
        }
    }

    /**
     * @param \Closure(float, float): array{float, float} $toImage
     */
    private function drawThings(MapCanvas $canvas, MapData $map, \Closure $toImage): void
    {
        foreach ($map->things as $thing) {
            $color = 0x4080FF;

            if ($thing->type === 1) {
                $color = 0x00FF00;
            }

            if (\in_array($thing->type, [3004, 3001, 9], true)) {
                $color = 0xFF0000;
            }

            [$x, $y] = $toImage($thing->x, $thing->y);

            $canvas->drawDot($x, $y, 2, $color);
        }
    }

    private function getSectorColor(MapData $map, int $index): int
    {
        $sector = $map->sectors[$index];
        $hue = (($index * 47) % 360) / 360;
        $value = 0.25 + 0.5 * \min(255, $sector->lightLevel) / 255;

        $channel = static fn(float $offset): int => (int) (
            255 * $value * (0.55 + 0.45 * \cos(2 * \M_PI * ($hue + $offset)))
        );

        return $channel(0.0) << 16 | $channel(1 / 3) << 8 | $channel(2 / 3);
    }

    /**
     * @return non-empty-string
     */
    private function getOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        \assert(\is_string($value) && $value !== '');

        return $value;
    }
}
