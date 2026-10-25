<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Play;

/**
 * Which key asks for which action.
 */
final readonly class KeyBindings
{
    public function __construct(
        /**
         * Actions by GLFW key codes
         *
         * @var array<int, KeyAction>
         */
        private array $actions,
    ) {}

    public function findAction(int $key): ?KeyAction
    {
        return $this->actions[$key] ?? null;
    }
}
