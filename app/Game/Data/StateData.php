<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Data;

/**
 * A state of the state machine of a thing or of a weapon sprite (`state_t` of `info.h`).
 *
 * @template A of \UnitEnum
 */
final readonly class StateData
{
    public function __construct(
        /** @var non-empty-string */
        public string $name,
        /** @var non-empty-string */
        public string $sprite,
        /**
         * The letter of the frame
         *
         * @var non-empty-string
         */
        public string $frame,
        public bool $isFullBright,
        /**
         * How many tics the state lasts, -1 for ever
         */
        public int $tics,
        /**
         * The state that follows, {@see null} removes the thing
         *
         * @var non-empty-string|null
         */
        public ?string $nextName,
        /**
         * @var A
         */
        public \UnitEnum $action,
    ) {}
}
