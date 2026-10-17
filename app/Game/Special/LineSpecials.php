<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

use Serafim\Doom\Game\Player;
use Serafim\Doom\Level\Geometry\WallPartType;
use Serafim\Doom\Level\LinedefFlag;
use Serafim\Doom\Level\LineGeometry;
use Serafim\Doom\Level\LineSpecial;
use Serafim\Doom\Level\SidedefState;

/**
 * The special of a line: a door that the player opens by using it, a switch, and lines that act when the player walks
 * over them. Only the specials that the first level has are known.
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

    private readonly LineGeometry $geometry;

    /**
     * @param array<string, true> $textures the names of the textures that exist, upper case
     */
    public function __construct(
        private readonly SpecialContext $context,
        private readonly SidedefState $sides,
        private readonly array $textures,
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

    /**
     * The player uses what is in front of the eyes: the first line with a special on the way, or a wall that stops the
     * way.
     */
    public function use(Player $player): void
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

                $this->useLine($line, $side);

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
     * The player moved from one place to another: the lines with a special that were crossed from their front side to
     * their back side act.
     */
    public function cross(float $fromX, float $fromY, float $toX, float $toY): void
    {
        foreach ($this->context->level->lineGrid->findLines(
            \min($fromX, $toX),
            \max($fromX, $toX),
            \min($fromY, $toY),
            \max($fromY, $toY),
        ) as $line) {
            if (LineSpecial::tryFrom($this->specials[$line])?->isWalkTrigger() !== true) {
                continue;
            }

            if ($this->geometry->isOnLeft($line, $fromX, $fromY) || !$this->geometry->isOnLeft($line, $toX, $toY)) {
                continue;
            }

            if ($this->geometry->findCrossing($line, $fromX, $fromY, $toX, $toY) === null) {
                continue;
            }

            $this->walkOver($line);
        }
    }

    private function useLine(int $line, int $side): void
    {
        if ($side !== 0) {
            return;
        }

        $linedef = $this->context->level->map->linedefs[$line];

        $special = LineSpecial::tryFrom($this->specials[$line]);

        if ($special === LineSpecial::DOOR_OPEN_WAIT_CLOSE && $linedef->leftSidedef !== null) {
            $this->useDoor($this->context->level->map->sidedefs[$linedef->leftSidedef]->sector);

            return;
        }

        if ($special === LineSpecial::EXIT_LEVEL) {
            $this->pressSwitch($linedef->rightSidedef);
            $this->specials[$line] = 0;
            $this->isExitRequested = true;
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

        foreach ($this->context->level->sectorsByTag[$linedef->tag] ?? [] as $sector) {
            if ($this->context->thinkers->findHolder($sector) !== null) {
                continue;
            }

            $thinker = new FloorThinker($this->context, $sector);

            if ($special === LineSpecial::LIFT_LOWER_WAIT_RAISE) {
                $thinker = new PlatformThinker($this->context, $sector);
            }

            $this->context->thinkers->add($thinker, $sector);
        }

        if ($special === LineSpecial::FLOOR_LOWER_TURBO) {
            $this->specials[$line] = 0;
        }
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
