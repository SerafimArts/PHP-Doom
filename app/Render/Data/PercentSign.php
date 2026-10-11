<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Data;

final readonly class PercentSign
{
    public function __construct(
        public string $picture,
        public int $x,
        public int $y,
    ) {}
}
