<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Play;

use Serafim\Doom\Command\Input\InputConfig;
use Serafim\Doom\Command\Input\KeyState;
use Serafim\Doom\Game\PlayerInput;

/**
 * Builds the input of a tic from the keys that are down and the mouse motion that was collected since the last tic.
 */
final class KeyboardInput
{
    private float $mouseTurn = 0.0;

    private bool $isUseRequested = false;

    /**
     * @var int<0, 3>
     */
    private int $weaponSlot = 0;

    public function __construct(
        private readonly KeyState $keys,
        private readonly InputConfig $config,
    ) {}

    /**
     * Adds a motion of the mouse in pixels, to the right is positive.
     */
    public function addMouseMotion(float $pixels): void
    {
        $this->mouseTurn -= $pixels * $this->config->mouseSensitivity;
    }

    /**
     * The use key was pressed: the next tic uses what is in front of the player.
     */
    public function requestUse(): void
    {
        $this->isUseRequested = true;
    }

    /**
     * The key of a weapon was pressed: the next tic takes the weapon.
     *
     * @param int<1, 3> $slot
     */
    public function requestWeapon(int $slot): void
    {
        $this->weaponSlot = $slot;
    }

    public function read(): PlayerInput
    {
        $movement = $this->config->movement;
        $isRunning = $this->keys->isKeyDown($movement->run);

        $forward = PlayerInput::WALK_FORWARD;
        $side = PlayerInput::WALK_SIDE;
        $keyTurn = $this->config->keyTurn;

        if ($isRunning) {
            $forward = PlayerInput::RUN_FORWARD;
            $side = PlayerInput::RUN_SIDE;
            $keyTurn *= $this->config->runTurnFactor;
        }

        $turn = $this->mouseTurn;
        $this->mouseTurn = 0.0;
        $isUsing = $this->isUseRequested;
        $this->isUseRequested = false;
        $weaponSlot = $this->weaponSlot;
        $this->weaponSlot = 0;
        $isFiring = $this->keys->isKeyDown($movement->fire) || $this->keys->isMouseButtonDown($movement->fireButton);

        if ($this->keys->isKeyDown($movement->turnLeft)) {
            $turn += $keyTurn;
        }

        if ($this->keys->isKeyDown($movement->turnRight)) {
            $turn -= $keyTurn;
        }

        return new PlayerInput(
            forwardMove: $this->axis($movement->forward, $movement->back) * $forward,
            sideMove: $this->axis($movement->right, $movement->left) * $side,
            turn: $turn,
            isUsing: $isUsing,
            weaponSlot: $weaponSlot,
            isFiring: $isFiring,
        );
    }

    /**
     * @return int<-1, 1>
     */
    private function axis(int $positive, int $negative): int
    {
        $value = 0;

        if ($this->keys->isKeyDown($positive)) {
            ++$value;
        }

        if ($this->keys->isKeyDown($negative)) {
            --$value;
        }

        return $value;
    }
}
