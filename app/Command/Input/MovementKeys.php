<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Input;

/**
 * The keys that hold a movement of the player, as GLFW key codes.
 */
final readonly class MovementKeys
{
    public function __construct(
        public int $forward,
        public int $back,
        public int $right,
        public int $left,
        public int $run,
        public int $fire,
        public int $fireButton,
        public int $turnLeft,
        public int $turnRight,
    ) {}
}
