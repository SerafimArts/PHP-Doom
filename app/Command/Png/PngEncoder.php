<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Png;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Serafim\Doom\Command
 */
final readonly class PngEncoder
{
    private const string SIGNATURE = "\x89PNG\r\n\x1A\n";

    private function __construct() {}

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param string $rgba four bytes per pixel in row-major order
     *
     * @return non-empty-string
     */
    public static function encode(int $width, int $height, string $rgba): string
    {
        $stride = $width * 4;
        $scanlines = '';

        for ($y = 0; $y < $height; ++$y) {
            $scanlines .= "\0" . \substr($rgba, $y * $stride, $stride);
        }

        return self::SIGNATURE
            . self::chunk('IHDR', \pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
            . self::chunk('IDAT', (string) \gzcompress($scanlines, 6))
            . self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        return \pack('N', \strlen($data)) . $type . $data . \pack('N', \crc32($type . $data));
    }
}
