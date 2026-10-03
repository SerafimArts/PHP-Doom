<?php

declare(strict_types=1);

namespace Local\WAD\Tests\Support;

final readonly class WadBuilder
{
    private function __construct() {}

    /**
     * @param list<array{string, string}> $lumps names and data in the order of the directory
     */
    public static function build(array $lumps, string $signature = 'IWAD'): string
    {
        $body = '';
        $directory = '';
        $position = 12;

        foreach ($lumps as [$name, $data]) {
            $directory .= \pack('VV', $position, \strlen($data)) . \str_pad($name, 8, "\0");
            $body .= $data;
            $position += \strlen($data);
        }

        return $signature . \pack('VV', \count($lumps), $position) . $body . $directory;
    }

    /**
     * @param list<list<array{int, string}>> $columns posts of every column: the first row and the pixel bytes
     */
    public static function buildPatch(int $width, int $height, int $leftOffset, int $topOffset, array $columns): string
    {
        $header = \pack('vvvv', $width, $height, $leftOffset & 0xFFFF, $topOffset & 0xFFFF);
        $offset = 8 + $width * 4;
        $table = '';
        $body = '';

        foreach ($columns as $posts) {
            $table .= \pack('V', $offset + \strlen($body));

            foreach ($posts as [$top, $pixels]) {
                $body .= \chr($top) . \chr(\strlen($pixels)) . "\0" . $pixels . "\0";
            }

            $body .= "\xFF";
        }

        return $header . $table . $body;
    }

    /**
     * @param list<string> $names
     */
    public static function buildPatchNames(array $names): string
    {
        $data = \pack('V', \count($names));

        foreach ($names as $name) {
            $data .= \str_pad($name, 8, "\0");
        }

        return $data;
    }

    /**
     * @param list<array{string, int, int, list<array{int, int, int}>}> $textures name, width, height and patches
     *        as the origin x, the origin y and the index into PNAMES
     */
    public static function buildTextures(array $textures): string
    {
        $offsets = '';
        $body = '';
        $start = 4 + \count($textures) * 4;

        foreach ($textures as [$name, $width, $height, $patches]) {
            $offsets .= \pack('V', $start + \strlen($body));
            $body .= \str_pad($name, 8, "\0") . \pack('VvvVv', 0, $width, $height, 0, \count($patches));

            foreach ($patches as [$x, $y, $index]) {
                $body .= \pack('vvvvv', $x & 0xFFFF, $y & 0xFFFF, $index, 1, 0);
            }
        }

        return \pack('V', \count($textures)) . $offsets . $body;
    }
}
