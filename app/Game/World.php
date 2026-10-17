<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

use Serafim\Doom\Game\Combat\WeaponSystem;
use Serafim\Doom\Level\Data\AnimationTable;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LevelState;

/**
 * The state of the game in a level: the sectors and the walls that change, the player and the time in tics.
 */
final class World
{
    private const float DEAD_VIEW_HEIGHT = 6.0;

    public readonly Level $level;

    public readonly Player $player;

    public readonly PlayerStats $stats;

    /**
     * The things that stand in the level, the ones that were picked up or destroyed leave the list at the end of a tic
     */
    public readonly ThingRegistry $things;

    /**
     * The weapon in the hands of the player
     */
    public readonly WeaponSystem $weapons;

    /**
     * Monsters in the level, what the player can kill
     *
     * @var int<0, max>
     */
    public readonly int $totalKills;

    /**
     * Items in the level that count for the result: bonuses and the like
     *
     * @var int<0, max>
     */
    public readonly int $totalItems;

    /**
     * The sectors whose flats animate, with the flats they started with
     *
     * @var array<int, string>
     */
    private readonly array $animatedFloors;

    /**
     * @var array<int, string>
     */
    private readonly array $animatedCeilings;

    /**
     * @var int<0, max>
     */
    private int $tic = 0;

    private PlayerView $previous;

    private bool $isDeathHandled = false;

    private bool $isRestartRequested = false;

    /**
     * @param int<0, max> $parSeconds the par time of the level in seconds
     */
    public function __construct(
        GameContext $context,
        public readonly LevelState $state,
        private readonly GameSystems $systems,
        private readonly AnimationTable $animations,
        private readonly int $parSeconds = 30,
    ) {
        $this->level = $context->level;
        $this->player = $context->player;
        $this->stats = $context->stats;
        $this->things = $context->things;
        $this->weapons = $systems->weapons;

        $this->totalKills = $this->countThings(
            static fn(Mobj $thing): bool => $thing->definition?->isCountedAsKill === true,
        );
        $this->totalItems = $this->countThings(
            static fn(Mobj $thing): bool => $thing->definition?->isCountedAsItem === true,
        );

        $this->animatedFloors = $this->findAnimated($this->state->sectors->floorFlats);
        $this->animatedCeilings = $this->findAnimated($this->state->sectors->ceilingFlats);

        $this->previous = $this->player->getView($this->tic);
    }

    public function tick(PlayerInput $input): void
    {
        $this->previous = $this->player->getView($this->tic);

        if ($this->stats->isDead()) {
            $this->tickDeath($input);
        } else {
            $this->tickPlayer($input);
        }

        $this->weapons->tick($input->isFiring && !$this->stats->isDead(), $this->tic);

        $this->things->replace($this->systems->pickups->update($this->player, $this->stats, $this->things->all()));
        $this->systems->missiles->tick($this->things->all());
        $this->systems->animator->animate($this->things->all());
        $this->things->removeFinished();
        $this->stats->tick();

        $this->systems->thinkers->run();
        $this->animateFlats();

        ++$this->tic;
    }

    /**
     * @return int<0, max>
     */
    public function getTic(): int
    {
        return $this->tic;
    }

    /**
     * The dead player asked to start the level again.
     */
    public function isRestartRequested(): bool
    {
        return $this->isRestartRequested;
    }

    public function isExitRequested(): bool
    {
        return $this->systems->lines->isExitRequested();
    }

    /**
     * What the player did in the level: the numbers of the intermission screen.
     */
    public function getResult(): LevelResult
    {
        return new LevelResult(
            $this->stats->kills,
            $this->totalKills,
            $this->stats->items,
            $this->totalItems,
            $this->stats->secrets,
            $this->getTotalSecrets(),
            \max(0, \intdiv($this->tic, TicClock::RATE)),
            $this->parSeconds,
        );
    }

    /**
     * @return int<0, max>
     */
    public function getTotalSecrets(): int
    {
        return $this->systems->sectorSpecials->totalSecrets;
    }

    /**
     * The view between the last two tics, `$alpha` is from 0 to 1.
     */
    public function getView(float $alpha): PlayerView
    {
        return $this->previous->interpolate($this->player->getView($this->tic), $alpha);
    }

    private function tickPlayer(PlayerInput $input): void
    {
        $weapon = Weapon::tryFrom($input->weaponSlot);

        if ($weapon !== null) {
            $this->stats->select($weapon);
        }

        if ($input->isUsing) {
            $this->systems->lines->use($this->player);
        }

        $fromX = $this->player->x;
        $fromY = $this->player->y;

        $this->systems->physics->tic($this->player, $input);
        $this->systems->lines->cross($fromX, $fromY, $this->player->x, $this->player->y);
        $this->systems->sectorSpecials->applyToPlayer($this->player, $this->stats, $this->tic);
    }

    /**
     * A dead player cannot move: the view sinks to the floor until the use key starts the level again
     * (`P_DeathThink`).
     */
    private function tickDeath(PlayerInput $input): void
    {
        if (!$this->isDeathHandled) {
            $this->isDeathHandled = true;
            $this->weapons->drop();
        }

        $this->player->viewHeight = \max(self::DEAD_VIEW_HEIGHT, $this->player->viewHeight - 1.0);

        if ($input->isUsing) {
            $this->isRestartRequested = true;
        }
    }

    private function animateFlats(): void
    {
        if ($this->tic % $this->animations->ticsPerFrame !== 0) {
            return;
        }

        foreach ($this->animatedFloors as $sector => $flat) {
            $this->state->sectors->floorFlats[$sector] = $this->animations->getFrame($flat, $this->tic);
        }

        foreach ($this->animatedCeilings as $sector => $flat) {
            $this->state->sectors->ceilingFlats[$sector] = $this->animations->getFrame($flat, $this->tic);
        }
    }

    /**
     * @param array<int, string> $flats
     *
     * @return array<int, string>
     */
    private function findAnimated(array $flats): array
    {
        $animated = [];

        foreach ($flats as $sector => $flat) {
            if ($this->animations->findCycle($flat) !== null) {
                $animated[$sector] = $flat;
            }
        }

        return $animated;
    }

    /**
     * @param callable(Mobj): bool $counts
     *
     * @return int<0, max>
     */
    private function countThings(callable $counts): int
    {
        $total = 0;

        foreach ($this->things as $thing) {
            if ($counts($thing)) {
                ++$total;
            }
        }

        return $total;
    }
}
