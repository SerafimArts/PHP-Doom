<?php

declare(strict_types=1);

namespace Local\WAD\Image;

use Local\WAD\Binary;
use Local\WAD\Exception\MalformedLumpException;

/**
 * Converts the column based patch format into a {@see Picture}.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\WAD
 */
final readonly class PictureDecoder
{
    private const int MAX_SIDE = 4096;

    private const int HEADER_SIZE = 8;

    private const int END_OF_COLUMN = 0xFF;

    private function __construct() {}

    /**
     * @throws MalformedLumpException in case of the data is not a valid patch
     */
    public static function decode(string $name, string $data): Picture
    {
        $size = \strlen($data);

        if ($size < self::HEADER_SIZE) {
            throw MalformedLumpException::becauseDataIsTruncated($name);
        }

        $width = Binary::uint16($data, 0);
        $height = Binary::uint16($data, 2);

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw MalformedLumpException::becauseValueIsInvalid($name, 'picture size');
        }

        $columnsEnd = self::HEADER_SIZE + $width * 4;

        if ($size < $columnsEnd) {
            throw MalformedLumpException::becauseDataIsTruncated($name);
        }

        $pixels = \str_repeat("\0", $width * $height);
        $mask = $pixels;

        for ($x = 0; $x < $width; ++$x) {
            $position = Binary::uint32($data, self::HEADER_SIZE + $x * 4);

            if ($position < $columnsEnd || $position >= $size) {
                throw MalformedLumpException::becauseReferenceIsOutOfRange($name, 'column offset', $position);
            }

            while (true) {
                if ($position + 1 > $size) {
                    throw MalformedLumpException::becauseDataIsTruncated($name);
                }

                $top = \ord($data[$position]);

                if ($top === self::END_OF_COLUMN) {
                    break;
                }

                if ($position + 3 > $size) {
                    throw MalformedLumpException::becauseDataIsTruncated($name);
                }

                $length = \ord($data[$position + 1]);
                $start = $position + 3;

                if ($start + $length > $size) {
                    throw MalformedLumpException::becauseDataIsTruncated($name);
                }

                for ($i = 0; $i < $length; ++$i) {
                    $y = $top + $i;

                    if ($y >= $height) {
                        break;
                    }

                    $cell = $y * $width + $x;
                    $pixels[$cell] = $data[$start + $i];
                    $mask[$cell] = "\x01";
                }

                $position = $start + $length + 1;
            }
        }

        return new Picture(
            name: $name,
            width: $width,
            height: $height,
            leftOffset: Binary::int16($data, 4),
            topOffset: Binary::int16($data, 6),
            pixels: $pixels,
            mask: $mask,
        );
    }
}
