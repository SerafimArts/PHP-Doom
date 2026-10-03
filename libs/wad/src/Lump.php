<?php

declare(strict_types=1);

namespace Local\WAD;

final readonly class Lump
{
    public function __construct(
        /** @var int<0, max> */
        public int $index,
        /**
         * Name in upper case without the padding
         */
        public string $name,
        /**
         * Byte offset of the data in the file
         *
         * @var int<0, max>
         */
        public int $offset,
        /** @var int<0, max> */
        public int $size,
    ) {}
}
