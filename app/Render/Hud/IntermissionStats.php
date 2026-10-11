<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Hud;

/**
 * The numbers of the screen after the exit of a level: what the player killed, found and how long it took.
 */
final readonly class IntermissionStats
{
    public function __construct(
        /** @var int<0, max> */
        public int $killPercent,
        /** @var int<0, max> */
        public int $itemPercent,
        /** @var int<0, max> */
        public int $secretPercent,
        /**
         * Time spent in the level in seconds
         *
         * @var int<0, max>
         */
        public int $seconds,
        /**
         * Par time in seconds
         *
         * @var int<0, max>
         */
        public int $parSeconds,
    ) {}
}
