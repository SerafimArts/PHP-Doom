<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\DumpLevel;

/**
 * An RGB raster with the few operations a map drawing needs: filled convex polygons, lines and dots.
 */
final class MapCanvas
{
    /**
     * @var array<int, int> pixels as 0xRRGGBB
     */
    private array $pixels;

    public function __construct(
        /** @var int<1, max> */
        public readonly int $width,
        /** @var int<1, max> */
        public readonly int $height,
        int $background = 0x101010,
    ) {
        $this->pixels = \array_fill(0, $width * $height, $background);
    }

    /**
     * @param list<array{float, float}> $points
     */
    public function fillPolygon(array $points, int $color): void
    {
        $lowest = \INF;
        $highest = -\INF;

        foreach ($points as [, $pointY]) {
            $lowest = \min($lowest, $pointY);
            $highest = \max($highest, $pointY);
        }

        $minY = (int) \max(0, \floor($lowest));
        $maxY = (int) \min($this->height - 1, \ceil($highest));
        $count = \count($points);

        for ($y = $minY; $y <= $maxY; ++$y) {
            $scan = $y + 0.5;
            $left = \INF;
            $right = -\INF;

            for ($i = 0; $i < $count; ++$i) {
                [$x1, $y1] = $points[$i];
                [$x2, $y2] = $points[($i + 1) % $count];

                if (($y1 <= $scan && $y2 > $scan) || ($y2 <= $scan && $y1 > $scan)) {
                    $x = $x1 + ($scan - $y1) / ($y2 - $y1) * ($x2 - $x1);
                    $left = \min($left, $x);
                    $right = \max($right, $x);
                }
            }

            if ($left > $right) {
                continue;
            }

            $from = (int) \max(0, \ceil($left - 0.5));
            $to = (int) \min($this->width - 1, \floor($right - 0.5));

            for ($x = $from; $x <= $to; ++$x) {
                $this->pixels[$y * $this->width + $x] = $color;
            }
        }
    }

    public function drawLine(float $x1, float $y1, float $x2, float $y2, int $color): void
    {
        $steps = (int) \ceil(\max(\abs($x2 - $x1), \abs($y2 - $y1)));

        for ($i = 0; $i <= $steps; ++$i) {
            $t = 1.0;

            if ($steps > 0) {
                $t = $i / $steps;
            }

            $this->setPixel((int) \round($x1 + ($x2 - $x1) * $t), (int) \round($y1 + ($y2 - $y1) * $t), $color);
        }
    }

    public function drawDot(float $x, float $y, int $radius, int $color): void
    {
        for ($dy = -$radius; $dy <= $radius; ++$dy) {
            for ($dx = -$radius; $dx <= $radius; ++$dx) {
                if ($dx * $dx + $dy * $dy <= $radius * $radius) {
                    $this->setPixel((int) \round($x) + $dx, (int) \round($y) + $dy, $color);
                }
            }
        }
    }

    /**
     * @return string 8-bit RGBA, row by row
     */
    public function toRgba(): string
    {
        $data = '';

        foreach ($this->pixels as $pixel) {
            $data .= \chr($pixel >> 16 & 0xFF) . \chr($pixel >> 8 & 0xFF) . \chr($pixel & 0xFF) . "\xFF";
        }

        return $data;
    }

    private function setPixel(int $x, int $y, int $color): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
            return;
        }

        $this->pixels[$y * $this->width + $x] = $color;
    }
}
