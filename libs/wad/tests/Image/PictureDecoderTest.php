<?php

declare(strict_types=1);

namespace Local\WAD\Tests\Image;

use Local\WAD\Exception\MalformedLumpException;
use Local\WAD\Image\PictureDecoder;
use Local\WAD\Tests\Support\WadBuilder;
use Local\WAD\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PictureDecoder::class)]
final class PictureDecoderTest extends TestCase
{
    public function testPostsAreDrawnIntoColumnsAndLeaveGapsTransparent(): void
    {
        // 2 columns, 4 rows. Column 0 has pixels in rows 1-2, column 1 has two posts: row 0 and rows 3.
        $data = WadBuilder::buildPatch(2, 4, -3, 7, [
            [[1, "\x0A\x0B"]],
            [[0, "\x0C"], [3, "\x0D"]],
        ]);

        $picture = PictureDecoder::decode('TEST', $data);

        self::assertSame('TEST', $picture->name);
        self::assertSame(2, $picture->width);
        self::assertSame(4, $picture->height);
        self::assertSame(-3, $picture->leftOffset);
        self::assertSame(7, $picture->topOffset);
        self::assertSame("\x00\x0C\x0A\x00\x0B\x00\x00\x0D", $picture->pixels);
        self::assertSame("\x00\x01\x01\x00\x01\x00\x00\x01", $picture->mask);
        self::assertFalse($picture->isSolid);
    }

    public function testFullColumnsMakeTheImageSolid(): void
    {
        $picture = PictureDecoder::decode('SOLID', WadBuilder::buildPatch(1, 2, 0, 0, [[[0, "\x01\x02"]]]));

        self::assertTrue($picture->isSolid);
    }

    public function testPostsBelowTheImageAreClipped(): void
    {
        $picture = PictureDecoder::decode('CLIP', WadBuilder::buildPatch(1, 2, 0, 0, [[[1, "\x01\x02\x03"]]]));

        self::assertSame("\x00\x01", $picture->pixels);
    }

    public function testTruncatedDataThrowsMalformedLumpException(): void
    {
        $data = WadBuilder::buildPatch(1, 2, 0, 0, [[[0, "\x01\x02"]]]);

        $this->expectException(MalformedLumpException::class);
        PictureDecoder::decode('BROKEN', \substr($data, 0, -3));
    }

    public function testColumnOffsetOutOfTheLumpThrowsMalformedLumpException(): void
    {
        $data = \pack('vvvv', 1, 1, 0, 0) . \pack('V', 500) . "\xFF";

        $this->expectException(MalformedLumpException::class);
        PictureDecoder::decode('BADOFFSET', $data);
    }

    public function testTooShortHeaderThrowsMalformedLumpException(): void
    {
        $this->expectException(MalformedLumpException::class);
        PictureDecoder::decode('TINY', 'abc');
    }
}
