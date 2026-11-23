<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

use Serafim\Doom\Game\KeyColor;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerStats;
use Serafim\Doom\Level\Geometry\WallPartType;
use Serafim\Doom\Level\LinedefFlag;
use Serafim\Doom\Level\LineGeometry;
use Serafim\Doom\Level\LineSpecial;
use Serafim\Doom\Level\SidedefState;

/**
 * The special of a line: a door that the player opens by using it, a switch, and lines that act when the player walks
 * over them.
 */
final class LineSpecials
{
    /**
     * How far from the player a line can be used
     */
    public const float USE_RANGE = 64.0;

    /**
     * The special of every line, a line that works once loses it
     *
     * @var array<int, int>
     */
    public array $specials;

    private bool $isExitRequested = false;

    private bool $isSecretExitRequested = false;

    private ?int $teleportTag = null;

    private readonly LineGeometry $geometry;

    /**
     * @param array<string, true> $textures the names of the textures that exist, upper case
     */
    public function __construct(
        private readonly SpecialContext $context,
        private readonly SidedefState $sides,
        private readonly array $textures,
        private readonly Collider $collider,
    ) {
        $this->geometry = new LineGeometry($context->level->map);
        $specials = [];

        foreach ($context->level->map->linedefs as $linedef) {
            $specials[] = $linedef->special;
        }

        $this->specials = $specials;
    }

    public function isExitRequested(): bool
    {
        return $this->isExitRequested;
    }

    public function isSecretExitRequested(): bool
    {
        return $this->isSecretExitRequested;
    }

    public function teleportPlayer(Player $player): bool
    {
        $tag = $this->teleportTag;
        $this->teleportTag = null;

        if ($tag === null) {
            return false;
        }

        $level = $this->context->level;

        foreach ($level->map->things as $thing) {
            if ($thing->type !== 14) {
                continue;
            }

            $sector = $level->findSector($thing->x, $thing->y);

            if ($level->map->sectors[$sector]->tag !== $tag) {
                continue;
            }

            $position = $this->collider->check($thing->x, $thing->y, $this->context->sectors->floorHeights[$sector]);

            if (!$position->isFree || $position->ceilingZ - $position->floorZ < Player::HEIGHT) {
                continue;
            }

            $player->x = $thing->x;
            $player->y = $thing->y;
            $player->z = $player->floorZ = $position->floorZ;
            $player->ceilingZ = $position->ceilingZ;
            $player->yaw = \deg2rad($thing->angle);
            $player->momentumX = $player->momentumY = $player->momentumZ = $player->bob = 0.0;

            return true;
        }

        return false;
    }

    /**
     * The player uses what is in front of the eyes: the first line with a special on the way, or a wall that stops the
     * way.
     */
    public function use(Player $player, PlayerStats $stats): void
    {
        $endX = $player->x + \cos($player->yaw) * self::USE_RANGE;
        $endY = $player->y + \sin($player->yaw) * self::USE_RANGE;

        $hits = [];

        foreach ($this->context->level->lineGrid->findLines(
            \min($player->x, $endX),
            \max($player->x, $endX),
            \min($player->y, $endY),
            \max($player->y, $endY),
        ) as $line) {
            $fraction = $this->geometry->findCrossing($line, $player->x, $player->y, $endX, $endY);

            if ($fraction !== null) {
                $hits[$line] = $fraction;
            }
        }

        \asort($hits);

        foreach (\array_keys($hits) as $line) {
            if ($this->specials[$line] !== 0) {
                $side = 0;

                if ($this->geometry->isOnLeft($line, $player->x, $player->y)) {
                    $side = 1;
                }

                $this->useLine($line, $side, $stats);

                return;
            }

            if (!$this->hasOpening($line)) {
                return;
            }
        }
    }

    /**
     * A monster that walks into a line uses it. Only a door that is used by hand opens, and not a secret one
     * (`P_UseSpecialLine` for a monster).
     *
     * @param int<0, max> $line
     *
     * @return bool the line did something
     */
    public function useByMonster(int $line): bool
    {
        $linedef = $this->context->level->map->linedefs[$line];

        $isDoor = LineSpecial::tryFrom($this->specials[$line]) === LineSpecial::DOOR_OPEN_WAIT_CLOSE;

        if (!$isDoor || $linedef->leftSidedef === null || ($linedef->flags & LinedefFlag::SECRET) !== 0) {
            return false;
        }

        $sector = $this->context->level->map->sidedefs[$linedef->leftSidedef]->sector;
        $holder = $this->context->thinkers->findHolder($sector);

        if ($holder instanceof DoorThinker) {
            $holder->useByMonster();

            return true;
        }

        if ($holder !== null) {
            return false;
        }

        $this->context->thinkers->add(new DoorThinker($this->context, $sector), $sector);

        return true;
    }

    /**
     * Activates the line specials crossed by the player.
     */
    public function cross(float $fromX, float $fromY, float $toX, float $toY): void
    {
        foreach ($this->context->level->lineGrid->findLines(
            \min($fromX, $toX),
            \max($fromX, $toX),
            \min($fromY, $toY),
            \max($fromY, $toY),
        ) as $line) {
            $special = LineSpecial::tryFrom($this->specials[$line]);
            if ($special?->isWalkTrigger() !== true) {
                continue;
            }

            $fromLeft = $this->geometry->isOnLeft($line, $fromX, $fromY);
            if ($fromLeft === $this->geometry->isOnLeft($line, $toX, $toY)
                || ($fromLeft && ($special === LineSpecial::TELEPORT
                    || $special === LineSpecial::FLOOR_LOWER_TURBO || $special === LineSpecial::LIFT_LOWER_WAIT_RAISE))) {
                continue;
            }

            if ($this->geometry->findCrossing($line, $fromX, $fromY, $toX, $toY) === null) {
                continue;
            }

            $this->walkOver($line);
        }
    }

    private function useLine(int $line, int $side, PlayerStats $stats): void
    {
        if ($side !== 0) {
            return;
        }

        $linedef = $this->context->level->map->linedefs[$line];

        $special = LineSpecial::tryFrom($this->specials[$line]);
        $key = match ($special) {
            LineSpecial::BLUE_DOOR, LineSpecial::OPEN_BLUE_DOOR => KeyColor::BLUE,
            LineSpecial::RED_DOOR, LineSpecial::OPEN_RED_DOOR => KeyColor::RED,
            LineSpecial::YELLOW_DOOR, LineSpecial::OPEN_YELLOW_DOOR => KeyColor::YELLOW,
            default => null,
        };

        if ($key !== null && !$stats->inventory->hasKey($key)) {
            $stats->feedback->say(\sprintf('You need the %s key.', $key->value));

            return;
        }

        if (($special === LineSpecial::DOOR_OPEN_WAIT_CLOSE || $special === LineSpecial::BLUE_DOOR
            || $special === LineSpecial::RED_DOOR || $special === LineSpecial::YELLOW_DOOR)
            && $linedef->leftSidedef !== null) {
            $this->useDoor($this->context->level->map->sidedefs[$linedef->leftSidedef]->sector);

            return;
        }

        if (($special === LineSpecial::OPEN_DOOR || $special === LineSpecial::OPEN_BLUE_DOOR
            || $special === LineSpecial::OPEN_RED_DOOR || $special === LineSpecial::OPEN_YELLOW_DOOR)
            && $linedef->leftSidedef !== null) {
            $sector = $this->context->level->map->sidedefs[$linedef->leftSidedef]->sector;

            if ($this->openDoor($sector)) {
                $this->specials[$line] = 0;
            }

            return;
        }

        if ($special?->isSectorSwitch() === true) {
            if ($this->activateTaggedSectors($linedef->tag, $special) && !$special->isRepeatableSwitch()) {
                $this->pressSwitch($linedef->rightSidedef);
                $this->specials[$line] = 0;
            }

            return;
        }

        if ($special === LineSpecial::EXIT_LEVEL || $special === LineSpecial::SECRET_EXIT) {
            $this->pressSwitch($linedef->rightSidedef);
            $this->specials[$line] = 0;
            $this->isExitRequested = true;
            $this->isSecretExitRequested = $special === LineSpecial::SECRET_EXIT;
        }
    }

    private function useDoor(int $sector): void
    {
        $holder = $this->context->thinkers->findHolder($sector);

        if ($holder instanceof DoorThinker) {
            $holder->use();

            return;
        }

        if ($holder !== null) {
            return;
        }

        $this->context->thinkers->add(new DoorThinker($this->context, $sector), $sector);
    }

    private function walkOver(int $line): void
    {
        $linedef = $this->context->level->map->linedefs[$line];
        $special = LineSpecial::tryFrom($this->specials[$line]);

        if ($special === LineSpecial::TELEPORT) {
            $this->teleportTag ??= $linedef->tag;

            return;
        }

        if ($special !== null && $this->activateTaggedSectors($linedef->tag, $special)
            && $special->isSingleUseWalkTrigger()) {
            $this->specials[$line] = 0;
        }
    }

    private function openDoor(int $sector): bool
    {
        if ($this->context->thinkers->findHolder($sector) !== null) {
            return false;
        }

        $this->context->thinkers->add(new DoorThinker($this->context, $sector, true), $sector);

        return true;
    }

    private function activateTaggedSectors(int $tag, LineSpecial $special): bool
    {
        $activated = false;

        foreach ($this->context->level->sectorsByTag[$tag] ?? [] as $sector) {
            if ($this->context->thinkers->findHolder($sector) !== null) {
                continue;
            }

            if ($special === LineSpecial::BUILD_STAIRS || $special === LineSpecial::WALK_BUILD_STAIRS) {
                $this->buildStairs($sector);
                $activated = true;

                continue;
            }

            $thinker = match ($special) {
                LineSpecial::LIFT_LOWER_WAIT_RAISE, LineSpecial::SWITCH_LIFT => new PlatformThinker($this->context, $sector),
                LineSpecial::WALK_OPEN_DOOR, LineSpecial::WALK_OPEN_DOOR_REPEAT,
                LineSpecial::SWITCH_OPEN_DOOR => new DoorThinker($this->context, $sector, true),
                LineSpecial::WALK_OPEN_WAIT_CLOSE, LineSpecial::SWITCH_OPEN_WAIT_CLOSE => new DoorThinker($this->context, $sector),
                LineSpecial::SWITCH_LOWER_FLOOR, LineSpecial::WALK_LOWER_FLOOR => new FloorThinker(
                    $this->context,
                    $sector,
                    $this->context->getLowestFloorAround($sector),
                ),
                LineSpecial::WALK_RAISE_FLOOR, LineSpecial::WALK_RAISE_TO_CEILING => new FloorThinker(
                    $this->context,
                    $sector,
                    \min($this->context->getLowestCeilingAround($sector), $this->context->sectors->ceilingHeights[$sector]),
                ),
                LineSpecial::SWITCH_RAISE_FLOOR, LineSpecial::SWITCH_RAISE_PLATFORM,
                LineSpecial::WALK_RAISE_PLATFORM => new FloorThinker(
                    $this->context,
                    $sector,
                    $this->context->getNextHigherFloor($sector),
                ),
                default => new FloorThinker($this->context, $sector),
            };
            $this->context->thinkers->add($thinker, $sector);
            $activated = true;
        }

        return $activated;
    }

    private function buildStairs(int $sector): void
    {
        $sectors = $this->context->sectors;
        $flat = $sectors->floorFlats[$sector];
        $height = $sectors->floorHeights[$sector];

        do {
            $height += 8;
            $this->context->thinkers->add(new FloorThinker($this->context, $sector, $height), $sector);
            $next = null;

            foreach ($this->context->level->neighbors[$sector] as $neighbor) {
                if ($sectors->floorFlats[$neighbor] === $flat && $this->context->thinkers->findHolder($neighbor) === null
                    && $sectors->floorHeights[$neighbor] < $height && $sectors->ceilingHeights[$neighbor] >= $height) {
                    $next = $neighbor;

                    break;
                }
            }

            $sector = $next;
        } while ($sector !== null);
    }

    private function pressSwitch(int $sidedef): void
    {
        foreach ([WallPartType::UPPER, WallPartType::MIDDLE, WallPartType::LOWER] as $type) {
            $texture = $this->sides->findTexture($sidedef, $type);

            if ($texture === null) {
                continue;
            }

            $alternative = $this->context->animations->findAlternative($texture);

            if ($alternative === null || !isset($this->textures[$alternative])) {
                continue;
            }

            $this->sides->setTexture($sidedef, $type, $alternative);

            return;
        }
    }

    /**
     * @param int<0, max> $line
     */
    private function hasOpening(int $line): bool
    {
        $opening = $this->geometry->getOpening($line, $this->context->sectors);

        return $opening !== null && $opening > 0;
    }
}
