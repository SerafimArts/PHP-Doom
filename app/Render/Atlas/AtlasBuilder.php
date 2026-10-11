<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Atlas;

use Serafim\Doom\Exception\AtlasException;

final class AtlasBuilder
{
    /**
     * @var array<non-empty-string, array{width: int<1, max>, height: int<1, max>, pixels: string, mask: string|null}>
     */
    private array $pictures = [];

    public function __construct(
        /** @var int<1, 4294967295> */
        private readonly int $size = 2048,
    ) {}

    /**
     * @param non-empty-string $key
     * @param string $pixels palette indices, one byte per texel
     * @param string|null $mask one byte per texel, zero is transparent, {@see null} is fully opaque
     *
     * @throws AtlasException in case of the picture is empty
     */
    public function add(string $key, int $width, int $height, string $pixels, ?string $mask = null): self
    {
        if ($width < 1 || $height < 1) {
            throw AtlasException::becausePictureIsEmpty($key);
        }

        $this->pictures[$key] = ['width' => $width, 'height' => $height, 'pixels' => $pixels, 'mask' => $mask];

        return $this;
    }

    /**
     * @throws AtlasException in case of the pictures do not fit
     */
    public function build(): TextureAtlas
    {
        $keys = \array_keys($this->pictures);

        \usort($keys, fn(string $a, string $b): int => $this->pictures[$b]['height'] <=> $this->pictures[$a]['height']);

        $rows = \array_fill(0, $this->size, \str_repeat("\0\0", $this->size));
        $placed = [];
        $shelfY = 0;
        $shelfHeight = 0;
        $cursorX = 0;

        foreach ($keys as $key) {
            $picture = $this->pictures[$key];

            if ($cursorX + $picture['width'] > $this->size) {
                $shelfY += $shelfHeight;
                $shelfHeight = 0;
                $cursorX = 0;
            }

            if ($picture['width'] > $this->size || $shelfY + $picture['height'] > $this->size) {
                throw AtlasException::becauseAtlasIsFull($key, $this->size);
            }

            $shelfHeight = \max($shelfHeight, $picture['height']);

            for ($y = 0; $y < $picture['height']; ++$y) {
                $rows[$shelfY + $y] = \substr_replace(
                    $rows[$shelfY + $y],
                    $this->interleave($picture, $y),
                    $cursorX * 2,
                    $picture['width'] * 2,
                );
            }

            $placed[$key] = new AtlasRect($cursorX, $shelfY, $picture['width'], $picture['height']);
            $cursorX += $picture['width'];
        }

        $rects = [];
        $numbers = [];

        foreach ($this->pictures as $key => $_) {
            $numbers[$key] = \count($rects);
            $rects[] = $placed[$key];
        }

        $height = \min(4_294_967_295, \max(1, $shelfY + $shelfHeight));

        return new TextureAtlas($this->size, $height, \implode('', \array_slice($rows, 0, $height)), $rects, $numbers);
    }

    /**
     * @param array{width: int<1, max>, height: int<1, max>, pixels: string, mask: string|null} $picture
     */
    private function interleave(array $picture, int $row): string
    {
        $indices = \substr($picture['pixels'], $row * $picture['width'], $picture['width']);
        $mask = $picture['mask'];

        if ($mask === null) {
            return \implode("\xFF", \str_split($indices)) . "\xFF";
        }

        $opacity = \substr($mask, $row * $picture['width'], $picture['width']);
        $line = '';

        for ($x = 0; $x < $picture['width']; ++$x) {
            $line .= $indices[$x];

            if ($opacity[$x] === "\0") {
                $line .= "\0";

                continue;
            }

            $line .= "\xFF";
        }

        return $line;
    }
}
