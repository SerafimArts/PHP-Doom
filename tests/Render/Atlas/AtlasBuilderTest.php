<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Atlas;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Exception\AtlasException;
use Serafim\Doom\Render\Atlas\AtlasBuilder;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(AtlasBuilder::class)]
final class AtlasBuilderTest extends TestCase
{
    public function testPicturesKeepTheirTexelsAtTheirRectangles(): void
    {
        $atlas = new AtlasBuilder(16)
            ->add('A', 2, 2, "\x01\x02\x03\x04")
            ->add('B', 3, 1, "\x0A\x0B\x0C", "\x01\x00\x01")
            ->build();

        foreach (['A' => "\x01\x02\x03\x04", 'B' => "\x0A\x0B\x0C"] as $key => $expected) {
            $rect = $atlas->rects[$atlas->getNumber($key)];
            $indices = '';
            $opacity = '';

            for ($y = 0; $y < $rect->height; ++$y) {
                for ($x = 0; $x < $rect->width; ++$x) {
                    $offset = (($rect->y + $y) * $atlas->width + $rect->x + $x) * 2;
                    $indices .= $atlas->pixels[$offset];
                    $opacity .= $atlas->pixels[$offset + 1];
                }
            }

            self::assertSame($expected, $indices, $key);

            if ($key === 'B') {
                self::assertSame("\xFF\x00\xFF", $opacity, 'the mask becomes the opacity');
            }
        }
    }

    public function testPicturesDoNotOverlap(): void
    {
        $builder = new AtlasBuilder(32);

        for ($i = 0; $i < 12; ++$i) {
            $width = 5 + $i % 4;
            $height = 4 + $i % 7;

            $builder->add('P' . $i, $width, $height, \str_repeat(\chr($i + 1), $width * $height));
        }

        $atlas = $builder->build();
        $taken = [];

        foreach ($atlas->rects as $number => $rect) {
            for ($y = $rect->y; $y < $rect->y + $rect->height; ++$y) {
                for ($x = $rect->x; $x < $rect->x + $rect->width; ++$x) {
                    self::assertArrayNotHasKey($y . ':' . $x, $taken, 'picture ' . $number);
                    $taken[$y . ':' . $x] = true;
                }
            }
        }
    }

    public function testPictureThatDoesNotFitThrowsAtlasException(): void
    {
        $this->expectException(AtlasException::class);

        new AtlasBuilder(4)->add('BIG', 5, 1, 'abcde')->build();
    }

    public function testUnknownPictureThrowsAtlasException(): void
    {
        $atlas = new AtlasBuilder(4)->build();

        $this->expectException(AtlasException::class);

        $atlas->getNumber('MISSING');
    }
}
