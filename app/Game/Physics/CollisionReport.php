<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Physics;

use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;

/**
 * Describes where the player stands and what blocks the box of the player there and a quarter of a unit away in every
 * direction, so that a player who does not move can be understood from a log.
 */
final readonly class CollisionReport
{
    /**
     * Distance of the probes from the position of the player
     */
    private const float PROBE_DISTANCE = 0.25;

    /**
     * @var list<array{float, float}> unit steps of the eight directions
     */
    private const array DIRECTIONS = [
        [1.0, 0.0],
        [1.0, 1.0],
        [0.0, 1.0],
        [-1.0, 1.0],
        [-1.0, 0.0],
        [-1.0, -1.0],
        [0.0, -1.0],
        [1.0, -1.0],
    ];

    public function __construct(
        private Level $level,
        private SectorState $sectors,
        private Collider $collider,
    ) {}

    /**
     * @param iterable<Mobj> $things the things of the level
     */
    public function describe(Player $player, iterable $things, int $tic): string
    {
        $sector = $this->level->findSectorAt($player->x, $player->y);

        $lines = [
            \sprintf(
                'tic %d: position %.4f %.4f z %.3f, floor %.1f, ceiling %.1f, momentum %.4f %.4f',
                $tic,
                $player->x,
                $player->y,
                $player->z,
                $player->floorZ,
                $player->ceilingZ,
                $player->momentumX,
                $player->momentumY,
            ),
            \sprintf(
                'sector %d: floor %.1f, ceiling %.1f',
                $sector,
                $this->sectors->floorHeights[$sector],
                $this->sectors->ceilingHeights[$sector],
            ),
            'here: ' . $this->describeProbe($player->x, $player->y, $player->z, $things),
        ];

        $free = 0;

        foreach (self::DIRECTIONS as $index => [$stepX, $stepY]) {
            $length = \hypot($stepX, $stepY);
            $x = $player->x + $stepX / $length * self::PROBE_DISTANCE;
            $y = $player->y + $stepY / $length * self::PROBE_DISTANCE;

            if ($this->collider->check($x, $y, $player->z)->isFree) {
                ++$free;
            }

            $lines[] = \sprintf('direction %d: %s', $index, $this->describeProbe($x, $y, $player->z, $things));
        }

        $lines[] = \sprintf('free directions: %d of %d', $free, \count(self::DIRECTIONS));

        return \implode("\n", $lines) . "\n";
    }

    /**
     * @param iterable<Mobj> $things
     */
    private function describeProbe(float $x, float $y, float $z, iterable $things): string
    {
        $check = $this->collider->check($x, $y, $z);

        if ($check->isFree) {
            return 'free';
        }

        $reasons = [];

        if ($check->blockingLine !== null) {
            $reasons[] = $this->describeLine($check->blockingLine);
        }

        foreach ($things as $thing) {
            $reach = Player::RADIUS + $thing->radius;

            if (!$thing->isSolid || $thing->isRemoved) {
                continue;
            }

            if (\abs($x - $thing->x) < $reach && \abs($y - $thing->y) < $reach) {
                $reasons[] = \sprintf('thing %d at %.1f %.1f', $thing->type, $thing->x, $thing->y);
            }
        }

        if ($reasons === []) {
            $reasons[] = \sprintf('no line (floor %.1f, ceiling %.1f)', $check->floorZ, $check->ceilingZ);
        }

        return 'blocked by ' . \implode(', ', $reasons);
    }

    private function describeLine(int $index): string
    {
        $map = $this->level->map;
        $line = $map->linedefs[$index];
        $from = $map->vertexes[$line->startVertex];
        $to = $map->vertexes[$line->endVertex];

        $sides = '';

        if ($line->leftSidedef === null) {
            $sides = ' one-sided';
        }

        return \sprintf(
            'line %d (%d,%d)-(%d,%d) flags %d special %d%s',
            $index,
            $from->x,
            $from->y,
            $to->x,
            $to->y,
            $line->flags,
            $line->special,
            $sides,
        );
    }
}
