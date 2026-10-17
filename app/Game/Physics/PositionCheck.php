<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Physics;

/**
 * Whether a player fits at a position and what is under and over it there.
 */
final readonly class PositionCheck
{
    public function __construct(
        public bool $isFree,
        public float $floorZ,
        public float $ceilingZ,
        /**
         * Lowest floor under the player: a monster would not step down there
         */
        public float $dropoffZ,
        /**
         * The line that prevents the move, {@see null} when the position is free
         *
         * @var int<0, max>|null
         */
        public ?int $blockingLine,
        /**
         * A solid thing stands where the player would be
         */
        public bool $isBlockedByThing = false,
        /**
         * The lines with a special that the body touches and that do not stop it by themselves: a monster uses them to
         * open a door
         *
         * @var list<int<0, max>>
         */
        public array $specialLines = [],
    ) {}
}
