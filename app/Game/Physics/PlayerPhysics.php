<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Physics;

use Serafim\Doom\Game\Data\Tuning\PhysicsTuning;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerInput;

/**
 * The movement of the player during a tic: turning, thrust, friction, gravity and the collision with walls, steps and
 * ceilings. A player that hits a wall slides along it.
 */
final class PlayerPhysics
{
    private const float EPSILON = 1.0E-9;

    public function __construct(
        private readonly Collider $collider,
        private readonly PhysicsTuning $tuning,
    ) {}

    public function tic(Player $player, PlayerInput $input): void
    {
        $player->yaw += $input->turn;

        $this->updateSurroundings($player);

        if ($player->isOnGround()) {
            $this->thrust($player, $player->yaw, $input->forwardMove);
            $this->thrust($player, $player->yaw - \M_PI / 2, $input->sideMove);
        }

        $this->moveHorizontally($player, $input->forwardMove === 0 && $input->sideMove === 0);
        $this->moveVertically($player);

        $player->bob = \min($this->tuning->maxBob, ($player->momentumX ** 2 + $player->momentumY ** 2) / 4);
    }

    private function thrust(Player $player, float $angle, int $command): void
    {
        $player->momentumX += \cos($angle) * $command * $this->tuning->thrust;
        $player->momentumY += \sin($angle) * $command * $this->tuning->thrust;
    }

    private function moveHorizontally(Player $player, bool $isIdle): void
    {
        $x = \max(-$this->tuning->maxSpeed, \min($this->tuning->maxSpeed, $player->momentumX));
        $y = \max(-$this->tuning->maxSpeed, \min($this->tuning->maxSpeed, $player->momentumY));

        $steps = \max(1, (int) \ceil(\max(\abs($x), \abs($y)) / $this->tuning->maxStep));

        for ($step = 0; $step < $steps; ++$step) {
            $direction = $this->slide($player, $x / $steps, $y / $steps);

            if ($direction === null) {
                continue;
            }

            $along = $player->momentumX * $direction[0] + $player->momentumY * $direction[1];
            $player->momentumX = $along * $direction[0];
            $player->momentumY = $along * $direction[1];
            $x = \max(-$this->tuning->maxSpeed, \min($this->tuning->maxSpeed, $player->momentumX));
            $y = \max(-$this->tuning->maxSpeed, \min($this->tuning->maxSpeed, $player->momentumY));
        }

        if (!$player->isOnGround()) {
            return;
        }

        $player->momentumX *= $this->tuning->friction;
        $player->momentumY *= $this->tuning->friction;

        $stopSpeed = $this->tuning->stopSpeed;

        if ($isIdle && \abs($player->momentumX) < $stopSpeed && \abs($player->momentumY) < $stopSpeed) {
            $player->momentumX = 0.0;
            $player->momentumY = 0.0;
        }
    }

    /**
     * Moves the player by the vector. Where a line is in the way the player stops at it and the rest of the move goes
     * along the line.
     *
     * @return array{float, float}|null the direction of the line that was hit, {@see null} if nothing was in the way
     */
    private function slide(Player $player, float $dx, float $dy): ?array
    {
        $direction = null;

        for ($attempt = 0; $attempt < $this->tuning->slideAttempts; ++$attempt) {
            if (\abs($dx) < self::EPSILON && \abs($dy) < self::EPSILON) {
                return $direction;
            }

            if ($this->collider->check($player->x + $dx, $player->y + $dy, $player->z)->isFree) {
                $player->x += $dx;
                $player->y += $dy;
                $this->updateSurroundings($player);

                return $direction;
            }

            [$free, $blocked] = $this->searchFreeFraction($player, $dx, $dy);

            $line = $this->collider
                ->check($player->x + $dx * $blocked, $player->y + $dy * $blocked, $player->z)
                ->blockingLine;

            $player->x += $dx * $free;
            $player->y += $dy * $free;
            $this->updateSurroundings($player);

            if ($line === null) {
                $this->stairStep($player, $dx * (1.0 - $free), $dy * (1.0 - $free));

                return $direction;
            }

            $lineDirection = $this->collider->getLineDirection($line);
            $direction ??= $lineDirection;

            $rest = 1.0 - $free;
            $along = ($dx * $lineDirection[0] + $dy * $lineDirection[1]) * $rest;
            $dx = $along * $lineDirection[0];
            $dy = $along * $lineDirection[1];
        }

        return $direction;
    }

    /**
     * Bisects the move to the farthest fraction where the player still fits.
     *
     * @return array{float, float} the last free fraction and the first blocked one
     */
    private function searchFreeFraction(Player $player, float $dx, float $dy): array
    {
        $free = 0.0;
        $blocked = 1.0;

        for ($i = 0; $i < $this->tuning->searchIterations; ++$i) {
            $middle = ($free + $blocked) / 2;

            $probe = $this->collider->check($player->x + $dx * $middle, $player->y + $dy * $middle, $player->z);

            if ($probe->isFree) {
                $free = $middle;
            } else {
                $blocked = $middle;
            }
        }

        return [$free, $blocked];
    }

    /**
     * A thing is in the way and no line tells where to slide: the player tries the sideways part of the move alone.
     */
    private function stairStep(Player $player, float $dx, float $dy): void
    {
        foreach ([[0.0, $dy], [$dx, 0.0]] as [$stepX, $stepY]) {
            if ($this->collider->check($player->x + $stepX, $player->y + $stepY, $player->z)->isFree) {
                $player->x += $stepX;
                $player->y += $stepY;
                $this->updateSurroundings($player);

                return;
            }
        }
    }

    private function moveVertically(Player $player): void
    {
        $this->updateSurroundings($player);

        $player->z += $player->momentumZ;

        if ($player->z <= $player->floorZ) {
            $player->z = $player->floorZ;
            $player->momentumZ = 0.0;
        } elseif ($player->momentumZ === 0.0) {
            $player->momentumZ = -$this->tuning->gravity * 2;
        } else {
            $player->momentumZ -= $this->tuning->gravity;
        }

        if ($player->z + Player::HEIGHT > $player->ceilingZ) {
            $player->z = $player->ceilingZ - Player::HEIGHT;
            $player->momentumZ = \min(0.0, $player->momentumZ);
        }
    }

    private function updateSurroundings(Player $player): void
    {
        $check = $this->collider->check($player->x, $player->y, $player->z);

        $player->floorZ = $check->floorZ;
        $player->ceilingZ = $check->ceilingZ;
    }
}
