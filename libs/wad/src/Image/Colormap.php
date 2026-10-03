<?php

declare(strict_types=1);

namespace Local\WAD\Image;

/**
 * The tables that map a palette index to the index of a darker (or specially tinted) color.
 */
final readonly class Colormap
{
    public function __construct(
        /**
         * Rows of 256 bytes. The first rows go from the full brightness to the darkest one,
         * the next ones are special effects.
         *
         * @var list<non-empty-string>
         */
        public array $rows,
    ) {}
}
