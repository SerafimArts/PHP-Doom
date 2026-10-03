<?php

declare(strict_types=1);

namespace Local\WAD\Image;

/**
 * 256 colors of the screen. One of the palettes of PLAYPAL.
 */
final readonly class Palette
{
    public const int SIZE = 256;

    public function __construct(
        /**
         * Red, green and blue bytes of the 256 colors one after another (768 bytes)
         *
         * @var non-empty-string
         */
        public string $rgb,
    ) {}

    /**
     * @param int<0, 255> $index
     *
     * @return array{red: int<0, 255>, green: int<0, 255>, blue: int<0, 255>}
     */
    public function getColor(int $index): array
    {
        $offset = $index * 3;

        return [
            'red' => \ord($this->rgb[$offset]),
            'green' => \ord($this->rgb[$offset + 1]),
            'blue' => \ord($this->rgb[$offset + 2]),
        ];
    }
}
