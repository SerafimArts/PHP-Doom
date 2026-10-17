<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * The state of the player: health, the weapon in the hands, the counters of the level result, what the player carries
 * and what the screen says about it.
 */
final class PlayerStats
{
    public const int MAX_HEALTH = 100;

    public const int MAX_BONUS_HEALTH = 200;

    public const int MAX_ARMOR = 200;

    public int $health = self::MAX_HEALTH;

    /**
     * The weapon in the hands (`readyweapon`)
     */
    public Weapon $weapon = Weapon::PISTOL;

    /**
     * The weapon the player is changing to, {@see null} when the weapon stays (`pendingweapon`)
     */
    public ?Weapon $pendingWeapon = null;

    /**
     * The fire button was down when the weapon was last ready (`attackdown`)
     */
    public bool $isAttackDown = false;

    /**
     * How many shots in a row the player fired without letting the button go (`refire`)
     *
     * @var int<0, max>
     */
    public int $refire = 0;

    /**
     * Light that a muzzle flash adds to the whole scene, in steps of the colormap (`extralight`)
     *
     * @var int<0, 2>
     */
    public int $extraLight = 0;

    /**
     * Monsters killed
     *
     * @var int<0, max>
     */
    public int $kills = 0;

    /**
     * Items picked up that count for the result of the level
     *
     * @var int<0, max>
     */
    public int $items = 0;

    /**
     * @var int<0, max>
     */
    public int $secrets = 0;

    public function __construct(
        public readonly Inventory $inventory = new Inventory(),
        public readonly ScreenFeedback $feedback = new ScreenFeedback(),
    ) {}

    public function damage(int $amount): void
    {
        $amount = $this->inventory->absorb($amount);

        $this->health = \max(0, $this->health - $amount);
        $this->feedback->hurt($amount);
    }

    /**
     * One tic of the timers of the screen.
     */
    public function tick(): void
    {
        $this->feedback->tick();
    }

    /**
     * Asks for the weapon if the player has it, the weapon in the hands is put down first.
     */
    public function select(Weapon $weapon): void
    {
        if ($weapon !== $this->weapon && $this->inventory->has($weapon)) {
            $this->pendingWeapon = $weapon;
        }
    }

    public function isDead(): bool
    {
        return $this->health <= 0;
    }
}
