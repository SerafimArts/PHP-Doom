<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Physics;

use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\ThingRegistry;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LinedefFlag;
use Serafim\Doom\Level\SectorState;

/**
 * Tells whether the player fits at a position. The lines near the player come from the blockmap, a line stops the
 * player when it is one-sided or marked as blocking, or when the opening between its sectors is too low, too high up or
 * too narrow for the player.
 */
final class Collider
{
    public const float STEP_HEIGHT = 24.0;

    /**
     * @var list<array{
     *     x: int,
     *     y: int,
     *     dx: int,
     *     dy: int,
     *     minX: int,
     *     maxX: int,
     *     minY: int,
     *     maxY: int,
     *     front: int,
     *     back: int|null,
     *     blocking: bool,
     *     blocksMonsters: bool,
     *     special: int,
     * }>
     */
    private readonly array $lines;

    public function __construct(
        private readonly Level $level,
        private readonly SectorState $sectors,
        private readonly ?ThingRegistry $things = null,
    ) {
        $map = $level->map;
        $lines = [];

        foreach ($map->linedefs as $linedef) {
            $from = $map->vertexes[$linedef->startVertex];
            $to = $map->vertexes[$linedef->endVertex];
            $back = null;

            if ($linedef->leftSidedef !== null) {
                $back = $map->sidedefs[$linedef->leftSidedef]->sector;
            }

            $lines[] = [
                'x' => $from->x,
                'y' => $from->y,
                'dx' => $to->x - $from->x,
                'dy' => $to->y - $from->y,
                'minX' => \min($from->x, $to->x),
                'maxX' => \max($from->x, $to->x),
                'minY' => \min($from->y, $to->y),
                'maxY' => \max($from->y, $to->y),
                'front' => $map->sidedefs[$linedef->rightSidedef]->sector,
                'back' => $back,
                'blocking' => ($linedef->flags & LinedefFlag::BLOCKING) !== 0,
                'blocksMonsters' => ($linedef->flags & LinedefFlag::BLOCK_MONSTERS) !== 0,
                'special' => $linedef->special,
            ];
        }

        $this->lines = $lines;
    }

    /**
     * Direction of a line as a unit vector.
     *
     * @param int<0, max> $line
     *
     * @return array{float, float}
     */
    public function getLineDirection(int $line): array
    {
        $length = \hypot($this->lines[$line]['dx'], $this->lines[$line]['dy']);

        if ($length === 0.0) {
            return [1.0, 0.0];
        }

        return [$this->lines[$line]['dx'] / $length, $this->lines[$line]['dy'] / $length];
    }

    /**
     * @param float $z the height of the feet, the player has to fit under the ceiling and may step up
     */
    public function check(float $x, float $y, float $z): PositionCheck
    {
        return $this->checkBody($x, $y, $z, Player::RADIUS, Player::HEIGHT);
    }

    /**
     * Tells whether a body of the size fits at a position.
     *
     * @param float $z the height of the feet
     * @param Mobj|null $body the thing that moves, it does not block itself
     * @param Player|null $player the player blocks a monster that moves
     * @param bool $isMonster lines that block monsters stop the body
     * @param bool $hasThings solid things stop the body
     */
    public function checkBody(
        float $x,
        float $y,
        float $z,
        float $radius,
        float $height,
        ?Mobj $body = null,
        ?Player $player = null,
        bool $isMonster = false,
        bool $hasThings = true,
    ): PositionCheck {
        $sector = $this->level->findSectorAt($x, $y);

        $floorZ = $this->sectors->floorHeights[$sector];
        $ceilingZ = $this->sectors->ceilingHeights[$sector];
        $dropoffZ = $floorZ;
        $floorLine = null;
        $ceilingLine = null;
        $blocker = null;

        $specials = [];

        $left = $x - $radius;
        $right = $x + $radius;
        $bottom = $y - $radius;
        $top = $y + $radius;

        foreach ($this->level->lineGrid->findLines($left, $right, $bottom, $top) as $index) {
            $line = $this->lines[$index];

            if (!$this->isTouching($line, $left, $right, $bottom, $top)) {
                continue;
            }

            if ($line['back'] === null || $line['blocking'] || ($isMonster && $line['blocksMonsters'])) {
                $blocker ??= $index;

                continue;
            }

            if ($line['special'] !== 0) {
                $specials[] = $index;
            }

            $frontFloor = $this->sectors->floorHeights[$line['front']];
            $backFloor = $this->sectors->floorHeights[$line['back']];
            $openTop = \min(
                $this->sectors->ceilingHeights[$line['front']],
                $this->sectors->ceilingHeights[$line['back']],
            );
            $openBottom = \max($frontFloor, $backFloor);

            if ($openTop < $ceilingZ) {
                $ceilingZ = $openTop;
                $ceilingLine = $index;
            }

            if ($openBottom > $floorZ) {
                $floorZ = $openBottom;
                $floorLine = $index;
            }

            $dropoffZ = \min($dropoffZ, $frontFloor, $backFloor);
        }

        $blocker ??= $this->findOpeningBlocker($z, $height, $floorZ, $ceilingZ, $floorLine, $ceilingLine);

        $isBlockedByThing = $hasThings && $this->isBlockedByThing($x, $y, $radius, $body, $player);

        return new PositionCheck(
            $blocker === null && !$isBlockedByThing,
            $floorZ,
            $ceilingZ,
            $dropoffZ,
            $blocker,
            $isBlockedByThing,
            $specials,
        );
    }

    /**
     * The line that makes the opening too small for the body: a step that is too high, or a ceiling that is too low.
     *
     * @param int<0, max>|null $floorLine
     * @param int<0, max>|null $ceilingLine
     *
     * @return int<0, max>|null
     */
    private function findOpeningBlocker(
        float $z,
        float $height,
        float $floorZ,
        float $ceilingZ,
        ?int $floorLine,
        ?int $ceilingLine,
    ): ?int {
        $blocker = null;

        if ($floorZ - $z > self::STEP_HEIGHT) {
            $blocker = $floorLine;
        }

        if ($blocker === null && ($ceilingZ - $floorZ < $height || $ceilingZ - $z < $height)) {
            $blocker = $ceilingLine ?? $floorLine;
        }

        return $blocker;
    }

    /**
     * @param array{
     *     x: int,
     *     y: int,
     *     dx: int,
     *     dy: int,
     *     minX: int,
     *     maxX: int,
     *     minY: int,
     *     maxY: int,
     *     front: int,
     *     back: int|null,
     *     blocking: bool,
     *     blocksMonsters: bool,
     *     special: int,
     * } $line
     */
    private function isTouching(array $line, float $left, float $right, float $bottom, float $top): bool
    {
        if ($right <= $line['minX'] || $left >= $line['maxX'] || $top <= $line['minY'] || $bottom >= $line['maxY']) {
            return false;
        }

        return $this->isBoxOnBothSides($line, $left, $right, $bottom, $top);
    }

    private function isBlockedByThing(float $x, float $y, float $radius, ?Mobj $body, ?Player $player): bool
    {
        if ($player !== null) {
            $reach = $radius + Player::RADIUS;

            if (\abs($x - $player->x) < $reach && \abs($y - $player->y) < $reach) {
                return true;
            }
        }

        if ($this->things === null) {
            return false;
        }

        foreach ($this->things->all() as $thing) {
            $reach = $radius + $thing->radius;

            if (
                $thing !== $body
                && $thing->isSolid
                && !$thing->isRemoved
                && \abs($x - $thing->x) < $reach
                && \abs($y - $thing->y) < $reach
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{
     *     x: int,
     *     y: int,
     *     dx: int,
     *     dy: int,
     *     minX: int,
     *     maxX: int,
     *     minY: int,
     *     maxY: int,
     *     front: int,
     *     back: int|null,
     *     blocking: bool,
     *     blocksMonsters: bool,
     *     special: int,
     * } $line
     */
    private function isBoxOnBothSides(array $line, float $left, float $right, float $bottom, float $top): bool
    {
        $isAhead = false;
        $isBehind = false;

        foreach ([[$left, $bottom], [$right, $bottom], [$left, $top], [$right, $top]] as [$cornerX, $cornerY]) {
            $side = $line['dx'] * ($cornerY - $line['y']) - $line['dy'] * ($cornerX - $line['x']);

            if ($side > 0.0) {
                $isAhead = true;
            } elseif ($side < 0.0) {
                $isBehind = true;
            }
        }

        return $isAhead && $isBehind;
    }
}
