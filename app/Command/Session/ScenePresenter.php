<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Session;

use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\Combat\Psprite;
use Serafim\Doom\Game\Data\WeaponCatalog;
use Serafim\Doom\Game\LevelResult;
use Serafim\Doom\Game\World;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Render\Hud\HudState;
use Serafim\Doom\Render\Hud\IntermissionStats;
use Serafim\Doom\Render\Hud\PspriteView;
use Serafim\Doom\Render\Scene\SceneState;
use Serafim\Doom\Render\Scene\SceneThing;

/**
 * Hands the state of the game to the renderer: the game does not know the renderer and the renderer does not know the
 * game, this is where they meet.
 */
final readonly class ScenePresenter
{
    public function __construct(
        private Level $level,
        private WeaponCatalog $weapons,
    ) {}

    public function present(World $world): SceneState
    {
        $lights = $world->state->sectors->lights;
        $things = [];

        foreach ($world->things as $thing) {
            if ($thing->isRemoved) {
                continue;
            }

            $things[] = new SceneThing(
                $thing->type,
                $thing->x,
                $thing->y,
                $thing->z,
                $thing->angle,
                $thing->radius,
                $thing->height,
                $thing->sprite,
                $thing->frame,
                $thing->isFullBright,
                $lights[$this->level->findSectorAt($thing->x, $thing->y)],
                $thing->isSolid,
                $thing->isCorpse,
            );
        }

        $player = $world->player;
        $view = $player->getView($world->getTic());
        $stats = $world->stats;

        return new SceneState(
            $things,
            $view->x,
            $view->y,
            $view->z,
            $view->yaw,
            $player->x,
            $player->y,
            $stats->extraLight,
            $world->weapons->flash->state !== null,
            $this->presentHud($world, $lights[$this->level->findSectorAt($player->x, $player->y)]),
        );
    }

    /**
     * The numbers of the intermission screen.
     */
    public function presentResult(LevelResult $result): IntermissionStats
    {
        return new IntermissionStats(
            $result->getKillPercent(),
            $result->getItemPercent(),
            $result->getSecretPercent(),
            $result->seconds,
            $result->parSeconds,
        );
    }

    private function presentHud(World $world, int $light): HudState
    {
        $stats = $world->stats;
        $inventory = $stats->inventory;

        $weaponAmmo = null;
        $ammoType = $this->weapons->get($stats->weapon)->ammo;

        if ($ammoType !== null) {
            $weaponAmmo = $inventory->countAmmo($ammoType);
        }

        return new HudState(
            $stats->health,
            $inventory->armor,
            $weaponAmmo,
            $inventory->countAmmo(AmmoType::BULLETS),
            AmmoType::BULLETS->getCapacity(),
            $inventory->countAmmo(AmmoType::SHELLS),
            AmmoType::SHELLS->getCapacity(),
            $inventory->owned,
            $stats->feedback->message,
            $stats->feedback->damageCount,
            $stats->isDead(),
            $world->getTic(),
            $light,
            $stats->extraLight,
            $this->presentPsprite($world->weapons->weapon),
            $this->presentPsprite($world->weapons->flash),
        );
    }

    private function presentPsprite(Psprite $psprite): ?PspriteView
    {
        if ($psprite->state === null) {
            return null;
        }

        return new PspriteView(
            $psprite->state->sprite,
            $psprite->state->frame,
            $psprite->state->isFullBright,
            $psprite->x,
            $psprite->y,
        );
    }
}
