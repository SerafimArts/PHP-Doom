<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Monster;

use Serafim\Doom\Game\Data\Tuning\MonsterTuning;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\PositionCheck;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\Random;
use Serafim\Doom\Game\Special\LineSpecials;

/**
 * Walks a monster: one step in the direction it faces, the choice of the direction that leads to the player, and the
 * doors that are in the way (`P_TryMove`, `P_Move`, `P_NewChaseDir`).
 */
final readonly class MonsterMover
{
    /**
     * A monster does not step down farther than this
     */
    private const float MAX_DROP = Collider::STEP_HEIGHT;

    /**
     * The direction to the north-west, north-east, south-west and south-east
     *
     * @var list<Direction>
     */
    private const array DIAGONALS = [
        Direction::NORTH_WEST,
        Direction::NORTH_EAST,
        Direction::SOUTH_WEST,
        Direction::SOUTH_EAST,
    ];

    /**
     * What a direction (east, north-east and so on, counter-clockwise) adds to the position for a unit of speed
     *
     * @var list<array{float, float}>
     */
    private array $steps;

    public function __construct(
        private Collider $collider,
        private LineSpecials $lines,
        private Player $player,
        private Random $random,
        private MonsterTuning $tuning,
    ) {
        $diagonal = $tuning->diagonal;

        $this->steps = [
            [1.0, 0.0],
            [$diagonal, $diagonal],
            [0.0, 1.0],
            [-$diagonal, $diagonal],
            [-1.0, 0.0],
            [-$diagonal, -$diagonal],
            [0.0, -1.0],
            [$diagonal, -$diagonal],
        ];
    }

    /**
     * Moves the monster to a place when it fits there.
     */
    public function tryMove(Mobj $thing, float $x, float $y): bool
    {
        return $this->place($thing, $x, $y, $this->check($thing, $x, $y));
    }

    /**
     * One step in the direction of the walk. A door in the way is opened, and that counts as a step that went well.
     */
    public function move(Mobj $thing): bool
    {
        if ($thing->ai->moveDirection === Direction::NONE) {
            return false;
        }

        $speed = $thing->definition->speed ?? 0.0;
        [$stepX, $stepY] = $this->steps[$thing->ai->moveDirection->value];

        $x = $thing->x + $speed * $stepX;
        $y = $thing->y + $speed * $stepY;

        $check = $this->check($thing, $x, $y);

        if ($this->place($thing, $x, $y, $check)) {
            return true;
        }

        if ($check->specialLines === []) {
            return false;
        }

        $thing->ai->moveDirection = Direction::NONE;
        $isOpened = false;

        foreach (\array_reverse($check->specialLines) as $line) {
            if ($this->lines->useByMonster($line)) {
                $isOpened = true;
            }
        }

        return $isOpened;
    }

    /**
     * Chooses a new direction toward a place: straight at it when that is free, otherwise along one of the axes, then
     * any other direction (`P_NewChaseDir`).
     */
    public function chooseDirection(Mobj $thing, float $targetX, float $targetY): void
    {
        $old = $thing->ai->moveDirection;
        $turnaround = $old->getOpposite();

        $deltaX = $targetX - $thing->x;
        $deltaY = $targetY - $thing->y;

        [$horizontal, $vertical] = $this->findAxes($deltaX, $deltaY);

        if ($horizontal !== Direction::NONE && $vertical !== Direction::NONE) {
            $thing->ai->moveDirection = self::DIAGONALS[((int) ($deltaY < 0.0) << 1) + (int) ($deltaX > 0.0)];

            if ($thing->ai->moveDirection !== $turnaround && $this->walk($thing)) {
                return;
            }
        }

        if ($this->random->nextByte() > 200 || \abs($deltaY) > \abs($deltaX)) {
            [$horizontal, $vertical] = [$vertical, $horizontal];
        }

        if ($this->tryWalking($thing, $this->withoutTurnaround([$horizontal, $vertical], $turnaround))) {
            return;
        }

        if ($old !== Direction::NONE && $this->tryWalking($thing, [$old])) {
            return;
        }

        $directions = Direction::getCompass();

        if (($this->random->nextByte() & 1) === 0) {
            $directions = \array_reverse($directions);
        }

        if ($this->tryWalking($thing, $this->withoutTurnaround($directions, $turnaround))) {
            return;
        }

        if ($turnaround !== Direction::NONE && $this->tryWalking($thing, [$turnaround])) {
            return;
        }

        $thing->ai->moveDirection = Direction::NONE;
    }

    /**
     * The direction along every axis that leads toward a place, {@see Direction::NONE} when the place is near enough.
     *
     * @return array{Direction, Direction} the horizontal and the vertical direction
     */
    private function findAxes(float $deltaX, float $deltaY): array
    {
        $horizontal = Direction::NONE;
        $vertical = Direction::NONE;

        if ($deltaX > $this->tuning->turnDistance) {
            $horizontal = Direction::EAST;
        } elseif ($deltaX < -$this->tuning->turnDistance) {
            $horizontal = Direction::WEST;
        }

        if ($deltaY < -$this->tuning->turnDistance) {
            $vertical = Direction::SOUTH;
        } elseif ($deltaY > $this->tuning->turnDistance) {
            $vertical = Direction::NORTH;
        }

        return [$horizontal, $vertical];
    }

    /**
     * @param list<Direction> $directions
     *
     * @return list<Direction> the same without the way back
     */
    private function withoutTurnaround(array $directions, Direction $turnaround): array
    {
        $kept = [];

        foreach ($directions as $direction) {
            if ($direction !== $turnaround) {
                $kept[] = $direction;
            }
        }

        return $kept;
    }

    /**
     * Walks in the first of the directions that is free.
     *
     * @param list<Direction> $directions
     */
    private function tryWalking(Mobj $thing, array $directions): bool
    {
        foreach ($directions as $direction) {
            if ($direction === Direction::NONE) {
                continue;
            }

            $thing->ai->moveDirection = $direction;

            if ($this->walk($thing)) {
                return true;
            }
        }

        return false;
    }

    private function check(Mobj $thing, float $x, float $y): PositionCheck
    {
        return $this->collider->checkBody(
            $x,
            $y,
            $thing->z,
            $thing->radius,
            $thing->height,
            $thing,
            $this->player,
            true,
        );
    }

    /**
     * Puts the monster where it fits, on the floor of the place.
     */
    private function place(Mobj $thing, float $x, float $y, PositionCheck $check): bool
    {
        if (!$check->isFree || $check->floorZ - $check->dropoffZ > self::MAX_DROP) {
            return false;
        }

        $thing->x = $x;
        $thing->y = $y;
        $thing->floorZ = $check->floorZ;
        $thing->ceilingZ = $check->ceilingZ;
        $thing->z = $check->floorZ;

        return true;
    }

    /**
     * A step that went well sets how long the monster keeps the direction (`P_TryWalk`).
     */
    private function walk(Mobj $thing): bool
    {
        if (!$this->move($thing)) {
            return false;
        }

        $thing->ai->moveCount = $this->random->nextByte() & 15;

        return true;
    }
}
