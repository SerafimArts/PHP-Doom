<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

use Serafim\Doom\Game\Data\Tuning\CombatTuning;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Level\SectorState;

/**
 * Shots that arrive at once: where a bullet or a punch ends and what a shooter aims at (`P_LineAttack` and
 * `P_AimLineAttack`). The shot goes along a ray and stops at a wall, at the edge of an opening it does not fit into, or
 * in the first thing that can be shot.
 */
final class Hitscan
{
    /**
     * Highest and lowest slope of the autoaim: 100 units over 160 units
     */
    private const float EPSILON = 1.0E-9;

    /**
     * @var list<array{x: float, y: float, dx: float, dy: float, front: int, back: int|null}>
     */
    private readonly array $lines;

    public function __construct(
        private readonly GameContext $context,
        private readonly CombatTuning $combat,
    ) {
        $map = $context->level->map;
        $lines = [];

        foreach ($map->linedefs as $linedef) {
            $from = $map->vertexes[$linedef->startVertex];
            $to = $map->vertexes[$linedef->endVertex];
            $back = null;

            if ($linedef->leftSidedef !== null) {
                $back = $map->sidedefs[$linedef->leftSidedef]->sector;
            }

            $lines[] = [
                'x' => (float) $from->x,
                'y' => (float) $from->y,
                'dx' => (float) ($to->x - $from->x),
                'dy' => (float) ($to->y - $from->y),
                'front' => $map->sidedefs[$linedef->rightSidedef]->sector,
                'back' => $back,
            ];
        }

        $this->lines = $lines;
    }

    /**
     * Finds the thing in the way of a ray that the shooter would hit, within the range of the vertical autoaim.
     *
     * @param float $z height of the muzzle
     * @param float $angle counter-clockwise from the east, in radians
     */
    public function aim(float $x, float $y, float $z, float $angle, float $range, ?Mobj $shooter = null): ?AimTarget
    {
        $top = $this->combat->aimSlope;
        $bottom = -$this->combat->aimSlope;

        foreach ($this->findIntercepts($x, $y, $angle, $range, $shooter) as [$frac, $line, $thing]) {
            $distance = $range * $frac;

            if ($line !== null) {
                $slopes = $this->narrowSlopes($line, $z, $distance, $top, $bottom);

                if ($slopes === null) {
                    return null;
                }

                [$top, $bottom] = $slopes;

                continue;
            }

            \assert($thing !== null);

            $target = $this->aimAtThing($thing, $z, $distance, $top, $bottom);

            if ($target !== null) {
                return $target;
            }
        }

        return null;
    }

    /**
     * What a line in the way leaves of the slopes at which the shot can still fly.
     *
     * @return array{float, float}|null the top and the bottom slope, {@see null} when the line shuts the shot
     */
    private function narrowSlopes(int $line, float $z, float $distance, float $top, float $bottom): ?array
    {
        $edge = $this->getOpening($line);

        if ($edge === null || $edge['bottom'] >= $edge['top']) {
            return null;
        }

        if ($edge['isFloorStep']) {
            $bottom = \max($bottom, ($edge['bottom'] - $z) / $distance);
        }

        if ($edge['isCeilingStep']) {
            $top = \min($top, ($edge['top'] - $z) / $distance);
        }

        if ($top <= $bottom) {
            return null;
        }

        return [$top, $bottom];
    }

    /**
     * The target that a thing offers inside of the slopes, aimed at the middle of the part that is in sight.
     */
    private function aimAtThing(Mobj $thing, float $z, float $distance, float $top, float $bottom): ?AimTarget
    {
        $thingTop = ($thing->z + $thing->height - $z) / $distance;

        if ($thingTop < $bottom) {
            return null;
        }

        $thingBottom = ($thing->z - $z) / $distance;

        if ($thingBottom > $top) {
            return null;
        }

        return new AimTarget($thing, (\min($thingTop, $top) + \max($thingBottom, $bottom)) / 2);
    }

    /**
     * Follows a shot that rises by `$slope` over every unit it runs and tells where it ends, {@see null} when it runs
     * out of range or leaves through the sky.
     *
     * @param float $z height of the muzzle
     * @param float $angle counter-clockwise from the east, in radians
     * @param bool $isIgnoringThings the shot passes through things and stops only at walls
     */
    public function shoot(
        float $x,
        float $y,
        float $z,
        float $angle,
        float $range,
        float $slope,
        ?Mobj $shooter = null,
        bool $isIgnoringThings = false,
    ): ?ShotHit {
        foreach ($this->findIntercepts($x, $y, $angle, $range, $shooter, $isIgnoringThings) as [$frac, $line, $thing]) {
            $distance = $range * $frac;

            if ($line !== null) {
                $edge = $this->getOpening($line);

                if ($edge !== null) {
                    $isBelow = $edge['isFloorStep'] && ($edge['bottom'] - $z) / $distance > $slope;
                    $isAbove = $edge['isCeilingStep'] && ($edge['top'] - $z) / $distance < $slope;

                    if (!$isBelow && !$isAbove) {
                        continue;
                    }
                }

                return $this->hitLine($line, $x, $y, $z, $angle, $range, $slope, $frac);
            }

            \assert($thing !== null);

            if (($thing->z + $thing->height - $z) / $distance < $slope || ($thing->z - $z) / $distance > $slope) {
                continue;
            }

            $closer = $frac - $this->combat->thingBackoff / $range;

            return new ShotHit(
                $x + \cos($angle) * $range * $closer,
                $y + \sin($angle) * $range * $closer,
                $z + $slope * $range * $closer,
                $thing,
            );
        }

        return null;
    }

    private function hitLine(
        int $line,
        float $x,
        float $y,
        float $z,
        float $angle,
        float $range,
        float $slope,
        float $frac,
    ): ?ShotHit {
        $closer = $frac - $this->combat->wallBackoff / $range;
        $hitX = $x + \cos($angle) * $range * $closer;
        $hitY = $y + \sin($angle) * $range * $closer;
        $hitZ = $z + $slope * $range * $closer;

        $front = $this->lines[$line]['front'];
        $back = $this->lines[$line]['back'];

        if ($this->context->sectors->ceilingFlats[$front] === SectorState::SKY_FLAT) {
            if ($hitZ > $this->context->sectors->ceilingHeights[$front]) {
                return null;
            }

            if ($back !== null && $this->context->sectors->ceilingFlats[$back] === SectorState::SKY_FLAT) {
                return null;
            }
        }

        return new ShotHit($hitX, $hitY, $hitZ);
    }

    /**
     * The opening of a two-sided line, {@see null} for a one-sided one.
     *
     * @return array{bottom: float, top: float, isFloorStep: bool, isCeilingStep: bool}|null
     */
    private function getOpening(int $line): ?array
    {
        $front = $this->lines[$line]['front'];
        $back = $this->lines[$line]['back'];

        if ($back === null) {
            return null;
        }

        $frontFloor = $this->context->sectors->floorHeights[$front];
        $backFloor = $this->context->sectors->floorHeights[$back];
        $frontCeiling = $this->context->sectors->ceilingHeights[$front];
        $backCeiling = $this->context->sectors->ceilingHeights[$back];

        return [
            'bottom' => (float) \max($frontFloor, $backFloor),
            'top' => (float) \min($frontCeiling, $backCeiling),
            'isFloorStep' => $frontFloor !== $backFloor,
            'isCeilingStep' => $frontCeiling !== $backCeiling,
        ];
    }

    /**
     * The lines and the things that a ray crosses, nearest first. The fraction is the part of the range at which the
     * ray crosses a line, or enters the box of a thing.
     *
     * @return list<array{float, int|null, Mobj|null}>
     */
    private function findIntercepts(
        float $x,
        float $y,
        float $angle,
        float $range,
        ?Mobj $shooter,
        bool $isIgnoringThings = false,
    ): array {
        $dx = \cos($angle) * $range;
        $dy = \sin($angle) * $range;

        $intercepts = $this->findLineIntercepts($x, $y, $dx, $dy);

        if (!$isIgnoringThings) {
            \array_push($intercepts, ...$this->findThingIntercepts($x, $y, $dx, $dy, $shooter));
        }

        \usort($intercepts, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        return $intercepts;
    }

    /**
     * @return list<array{float, int, null}> the fraction of the ray, the line and no thing
     */
    private function findLineIntercepts(float $x, float $y, float $dx, float $dy): array
    {
        $intercepts = [];

        $candidates = $this->context->level->lineGrid->findLines(
            \min($x, $x + $dx),
            \max($x, $x + $dx),
            \min($y, $y + $dy),
            \max($y, $y + $dy),
        );

        foreach ($candidates as $index) {
            $line = $this->lines[$index];

            $denominator = $dx * $line['dy'] - $dy * $line['dx'];

            if (\abs($denominator) < self::EPSILON) {
                continue;
            }

            $fromX = $line['x'] - $x;
            $fromY = $line['y'] - $y;

            $frac = ($fromX * $line['dy'] - $fromY * $line['dx']) / $denominator;
            $along = ($fromX * $dy - $fromY * $dx) / $denominator;

            if ($frac > 0.0 && $frac <= 1.0 && $along >= 0.0 && $along <= 1.0) {
                $intercepts[] = [$frac, $index, null];
            }
        }

        return $intercepts;
    }

    /**
     * @return list<array{float, null, Mobj}> the fraction of the ray, no line and the thing
     */
    private function findThingIntercepts(float $x, float $y, float $dx, float $dy, ?Mobj $shooter): array
    {
        $intercepts = [];

        foreach ($this->context->things->all() as $thing) {
            if ($thing === $shooter || !$thing->isShootable || $thing->isRemoved) {
                continue;
            }

            $frac = $this->getEntry($x, $y, $dx, $dy, $thing);

            if ($frac !== null) {
                $intercepts[] = [$frac, null, $thing];
            }
        }

        return $intercepts;
    }

    /**
     * The part of the ray at which it enters the square around a thing, {@see null} when the ray misses it.
     */
    private function getEntry(float $x, float $y, float $dx, float $dy, Mobj $thing): ?float
    {
        return $this->getBoxEntry($x, $y, $dx, $dy, $thing->x, $thing->y, $thing->radius);
    }

    /**
     * The part of a ray that goes from a point by a step at which it enters the square around a center, {@see null}
     * when the ray misses the square.
     */
    public function getBoxEntry(
        float $x,
        float $y,
        float $dx,
        float $dy,
        float $centerX,
        float $centerY,
        float $radius,
    ): ?float {
        $near = 0.0;
        $far = 1.0;

        foreach ([[$x, $dx, $centerX], [$y, $dy, $centerY]] as [$origin, $delta, $center]) {
            $low = $center - $radius - $origin;
            $high = $center + $radius - $origin;

            if (\abs($delta) < self::EPSILON) {
                if ($low > 0.0 || $high < 0.0) {
                    return null;
                }

                continue;
            }

            $enter = $low / $delta;
            $leave = $high / $delta;

            if ($enter > $leave) {
                [$enter, $leave] = [$leave, $enter];
            }

            $near = \max($near, $enter);
            $far = \min($far, $leave);

            if ($near > $far) {
                return null;
            }
        }

        return \max($near, self::EPSILON);
    }
}
