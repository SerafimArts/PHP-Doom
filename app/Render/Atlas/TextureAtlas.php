<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Atlas;

use Serafim\Doom\Exception\AtlasException;

/**
 * Indexed-color pictures packed into one image. A texel has two bytes: the palette index and the opacity (255 or 0).
 * The shaders find a picture by its number and address it with `texelFetch`, so no filtering or bleeding is possible.
 */
final readonly class TextureAtlas
{
    public function __construct(
        /** @var int<1, 4294967295> */
        public int $width,
        /** @var int<1, 4294967295> */
        public int $height,
        public string $pixels,
        /**
         * By the number of a picture
         *
         * @var list<AtlasRect>
         */
        public array $rects,
        /** @var array<non-empty-string, int<0, max>> */
        private array $numbers,
    ) {}

    /**
     * @param non-empty-string $key
     *
     * @return int<0, max>|null
     */
    public function findNumber(string $key): ?int
    {
        return $this->numbers[$key] ?? null;
    }

    /**
     * @param non-empty-string $key
     *
     * @return int<0, max>
     * @throws AtlasException in case of the atlas has no such picture
     */
    public function getNumber(string $key): int
    {
        return $this->findNumber($key) ?? throw AtlasException::becausePictureNotFound($key);
    }

    /**
     * @return array<non-empty-string, int<0, max>> numbers of the pictures by their keys
     */
    public function getNumbers(): array
    {
        return $this->numbers;
    }

    /**
     * The rectangles as the shader reads them: four 32-bit unsigned integers for every picture.
     */
    public function packRects(): string
    {
        $data = '';

        foreach ($this->rects as $rect) {
            $data .= \pack('V4', $rect->x, $rect->y, $rect->width, $rect->height);
        }

        return $data;
    }
}
