<?php

declare(strict_types=1);

namespace Local\GPU\Window;

final readonly class WindowOptions
{
    public function __construct(
        /** @var non-empty-string */
        public string $title = 'Window',
        /** @var int<1, max> */
        public int $width = 1280,
        /** @var int<1, max> */
        public int $height = 720,
        public bool $isResizable = true,
    ) {}
}
