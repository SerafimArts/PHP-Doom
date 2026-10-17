<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

use Serafim\Doom\Game\AmmoType;
use Serafim\Doom\Game\Data\StateData;
use Serafim\Doom\Game\Data\StateTable;
use Serafim\Doom\Game\Data\Tuning;
use Serafim\Doom\Game\Data\WeaponCatalog;
use Serafim\Doom\Game\Data\WeaponInfo;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\Weapon;

/**
 * The weapon in the hands of the player: the states that raise, hold, fire and lower it (`p_pspr.c`) and the shots they
 * make. The fist, the pistol and the shotgun hit at once, bullets and punches end in a thing or in a wall.
 */
final class WeaponSystem
{
    public readonly Psprite $weapon;

    public readonly Psprite $flash;

    private bool $isFiring = false;

    private int $tic = 0;

    private float $bulletSlope = 0.0;

    public function __construct(
        private readonly GameContext $context,
        private readonly Hitscan $hitscan,
        private readonly DamageSystem $damage,
        private readonly Impacts $impacts,
        /** @var StateTable<PspriteAction> */
        private readonly StateTable $psprites,
        private readonly WeaponCatalog $catalog,
        private readonly Tuning $tuning,
        /**
         * Called when the player starts a shot, the noise wakes the monsters
         *
         * @var (\Closure(): void)|null
         */
        private readonly ?\Closure $onShot = null,
    ) {
        $this->weapon = new Psprite();
        $this->flash = new Psprite();
    }

    /**
     * Raises the weapon of the player from the bottom of the screen (`P_SetupPsprites`).
     */
    public function bringUp(): void
    {
        $this->weapon->state = null;
        $this->flash->state = null;

        $this->bringUpWeapon();
    }

    /**
     * Puts the weapon away: the player is dead (`P_DropWeapon`).
     */
    public function drop(): void
    {
        $this->setState($this->weapon, $this->getInfo()->down);
    }

    /**
     * The ammunition the weapon in the hands uses, {@see null} for a weapon that needs none.
     */
    public function getAmmoType(): ?AmmoType
    {
        return $this->getInfo()->ammo;
    }

    /**
     * One tic of the weapon (`P_MovePsprites`).
     *
     * @param bool $isFiring the fire button is down
     */
    public function tick(bool $isFiring, int $tic): void
    {
        $this->isFiring = $isFiring;
        $this->tic = $tic;

        foreach ([$this->weapon, $this->flash] as $psprite) {
            if ($psprite->state === null || $psprite->tics === 0) {
                continue;
            }

            if (--$psprite->tics === 0) {
                $this->setState($psprite, $this->psprites->getNext($psprite->state));
            }
        }

        $this->flash->x = $this->weapon->x;
        $this->flash->y = $this->weapon->y;
    }

    /**
     * Puts a sprite into a state and runs the action of the state. A state without tics passes at once.
     *
     * @param StateData<PspriteAction>|null $state
     */
    private function setState(Psprite $psprite, ?StateData $state): void
    {
        do {
            if ($state === null) {
                $psprite->state = null;

                return;
            }

            $psprite->state = $state;
            $psprite->tics = $state->tics;

            $this->run($psprite, $state->action);

            if ($psprite->state === null) {
                return;
            }

            $state = $this->psprites->getNext($psprite->state);
        } while ($psprite->tics === 0);
    }

    private function run(Psprite $psprite, PspriteAction $action): void
    {
        $light = $action->getExtraLight();

        if ($light !== null) {
            $this->context->stats->extraLight = $light;

            return;
        }

        switch ($action) {
            case PspriteAction::READY:
                $this->runReady($psprite);

                break;
            case PspriteAction::LOWER:
                $this->runLower($psprite);

                break;
            case PspriteAction::RAISE:
                $this->runRaise($psprite);

                break;
            case PspriteAction::PUNCH:
                $this->runPunch();

                break;
            case PspriteAction::FIRE_PISTOL:
                $this->runFirePistol();

                break;
            case PspriteAction::FIRE_SHOTGUN:
                $this->runFireShotgun();

                break;
            case PspriteAction::REFIRE:
                $this->runRefire();

                break;
            default:
                break;
        }
    }

    private function runReady(Psprite $psprite): void
    {
        if ($this->context->stats->pendingWeapon !== null || $this->context->stats->isDead()) {
            $this->setState($psprite, $this->getInfo()->down);

            return;
        }

        if ($this->isFiring) {
            $this->context->stats->isAttackDown = true;
            $this->fire();

            return;
        }

        $this->context->stats->isAttackDown = false;

        $angle = 2 * \M_PI * $this->tic / 64;

        $psprite->x = 1.0 + $this->context->player->bob * \cos($angle);
        $psprite->y = $this->tuning->weapon->top + $this->context->player->bob * \sin(\fmod($angle, \M_PI));
    }

    private function runLower(Psprite $psprite): void
    {
        $psprite->y += $this->tuning->weapon->lowerSpeed;

        if ($psprite->y < $this->tuning->weapon->bottom) {
            return;
        }

        if ($this->context->stats->isDead()) {
            $psprite->y = $this->tuning->weapon->bottom;

            return;
        }

        $this->bringUpWeapon();
    }

    private function runRaise(Psprite $psprite): void
    {
        $psprite->y -= $this->tuning->weapon->raiseSpeed;

        if ($psprite->y > $this->tuning->weapon->top) {
            return;
        }

        $psprite->y = $this->tuning->weapon->top;

        $this->setState($psprite, $this->getInfo()->ready);
    }

    private function runRefire(): void
    {
        if ($this->isFiring && $this->context->stats->pendingWeapon === null && !$this->context->stats->isDead()) {
            ++$this->context->stats->refire;
            $this->fire();

            return;
        }

        $this->context->stats->refire = 0;
        $this->checkAmmo();
    }

    /**
     * Changes to the weapon that was asked for and raises it (`P_BringUpWeapon`).
     */
    private function bringUpWeapon(): void
    {
        $weapon = $this->context->stats->pendingWeapon ?? $this->context->stats->weapon;

        $this->context->stats->weapon = $weapon;
        $this->context->stats->pendingWeapon = null;

        $this->weapon->y = $this->tuning->weapon->bottom;
        $this->setState($this->weapon, $this->catalog->get($weapon)->up);
    }

    /**
     * Starts a shot when the weapon has the ammunition for it (`P_FireWeapon`).
     */
    private function fire(): void
    {
        if (!$this->checkAmmo()) {
            return;
        }

        $this->setState($this->weapon, $this->getInfo()->attack);
        $this->onShot?->__invoke();
    }

    /**
     * Tells whether the weapon can shoot. Without ammunition the player changes to another weapon, the best one that
     * has some (`P_CheckAmmo`).
     */
    private function checkAmmo(): bool
    {
        $type = $this->getInfo()->ammo;

        if ($type === null || ($this->context->stats->inventory->ammo[$type->value] ?? 0) >= 1) {
            return true;
        }

        if (
            ($this->context->stats->inventory->owned[Weapon::SHOTGUN->value] ?? false)
            && ($this->context->stats->inventory->ammo[AmmoType::SHELLS->value] ?? 0) > 0
        ) {
            $this->context->stats->pendingWeapon = Weapon::SHOTGUN;
        } elseif (($this->context->stats->inventory->ammo[AmmoType::BULLETS->value] ?? 0) > 0) {
            $this->context->stats->pendingWeapon = Weapon::PISTOL;
        } else {
            $this->context->stats->pendingWeapon = Weapon::FIST;
        }

        $this->setState($this->weapon, $this->getInfo()->down);

        return false;
    }

    private function runPunch(): void
    {
        $damage = 2 * ($this->context->random->nextByte() % 10 + 1);
        $angle = $this->context->player->yaw + $this->getSpread();

        $target = $this->hitscan->aim(
            $this->context->player->x,
            $this->context->player->y,
            $this->context->player->z + $this->getMuzzleHeight(),
            $angle,
            $this->tuning->combat->meleeRange,
        );

        $slope = 0.0;

        if ($target !== null) {
            $slope = $target->slope;
        }

        $this->lineAttack($angle, $this->tuning->combat->meleeRange, $slope, $damage);

        if ($target !== null) {
            $player = $this->context->player;
            $player->yaw = \atan2($target->target->y - $player->y, $target->target->x - $player->x);
        }
    }

    private function runFirePistol(): void
    {
        $this->useAmmo();
        $this->setState($this->flash, $this->getInfo()->flash);

        $this->aimBullets();
        $this->shootBullet($this->context->stats->refire === 0);
    }

    private function runFireShotgun(): void
    {
        $this->useAmmo();
        $this->setState($this->flash, $this->getInfo()->flash);

        $this->aimBullets();

        for ($pellet = 0; $pellet < $this->tuning->weapon->pellets; ++$pellet) {
            $this->shootBullet(false);
        }
    }

    private function useAmmo(): void
    {
        $type = $this->getInfo()->ammo;

        if ($type !== null) {
            $inventory = $this->context->stats->inventory;
            $inventory->ammo[$type->value] = \max(0, ($inventory->ammo[$type->value] ?? 0) - 1);
        }
    }

    /**
     * Looks for what to shoot at straight ahead and a bit to both sides (`P_BulletSlope`).
     */
    private function aimBullets(): void
    {
        $this->bulletSlope = 0.0;

        $searchAngle = \M_PI / $this->tuning->weapon->aimSearchDivisor;

        foreach ([0.0, $searchAngle, -$searchAngle] as $offset) {
            $target = $this->hitscan->aim(
                $this->context->player->x,
                $this->context->player->y,
                $this->context->player->z + $this->getMuzzleHeight(),
                $this->context->player->yaw + $offset,
                $this->tuning->weapon->bulletAimRange,
            );

            if ($target !== null) {
                $this->bulletSlope = $target->slope;

                return;
            }
        }
    }

    private function shootBullet(bool $isAccurate): void
    {
        $damage = 5 * ($this->context->random->nextByte() % 3 + 1);
        $angle = $this->context->player->yaw;

        if (!$isAccurate) {
            $angle += $this->getSpread();
        }

        $this->lineAttack($angle, $this->tuning->combat->missileRange, $this->bulletSlope, $damage);
    }

    private function getSpread(): float
    {
        $first = $this->context->random->nextByte();
        $second = $this->context->random->nextByte();

        return ($first - $second) * $this->tuning->combat->getPlayerSpreadStep();
    }

    /**
     * Shoots along a ray and lets the hit show: blood for what bleeds, a puff for the rest (`P_LineAttack`).
     */
    private function lineAttack(float $angle, float $range, float $slope, int $damage): void
    {
        $hit = $this->hitscan->shoot(
            $this->context->player->x,
            $this->context->player->y,
            $this->context->player->z + $this->getMuzzleHeight(),
            $angle,
            $range,
            $slope,
        );

        if ($hit === null) {
            return;
        }

        if ($hit->thing === null) {
            $this->impacts->puff($hit, $range === $this->tuning->combat->meleeRange);

            return;
        }

        if ($hit->thing->definition?->hasBlood === false) {
            $this->impacts->puff($hit, false);
        } else {
            $this->impacts->blood($hit, $damage);
        }

        $this->damage->damage($hit->thing, $damage);
    }

    private function getInfo(): WeaponInfo
    {
        return $this->catalog->get($this->context->stats->weapon);
    }

    /**
     * Height of the muzzle over the feet: the middle of the player and a bit more
     */
    private function getMuzzleHeight(): float
    {
        return Player::HEIGHT / 2 + $this->tuning->weapon->muzzleHeightOffset;
    }
}
