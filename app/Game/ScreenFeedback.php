<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * What the screen of the player says about what happens: the message of a pickup and the red and gold tints of the
 * palette.
 */
final class ScreenFeedback
{
    public const int MESSAGE_TICS = 140;

    /**
     * What the screen says (what was picked up), {@see null} when there is nothing to say
     */
    public ?string $message = null;

    /**
     * How many tics the message stays on the screen
     *
     * @var int<0, max>
     */
    public int $messageTics = 0;

    /**
     * The screen is red while the player is hurt: how much hurt remains
     *
     * @var int<0, 100>
     */
    public int $damageCount = 0;

    /**
     * The screen is gold while the player picks things up: how much of it remains
     *
     * @var int<0, max>
     */
    public int $bonusCount = 0;

    public function say(string $message): void
    {
        $this->message = $message;
        $this->messageTics = self::MESSAGE_TICS;
    }

    /**
     * The player lost health.
     */
    public function hurt(int $amount): void
    {
        $this->damageCount = \max(0, \min(100, $this->damageCount + $amount));
    }

    /**
     * One tic of the timers: the message, the red and the gold of the palette.
     */
    public function tick(): void
    {
        if ($this->messageTics > 0 && --$this->messageTics === 0) {
            $this->message = null;
        }

        $this->damageCount = \max(0, $this->damageCount - 1);
        $this->bonusCount = \max(0, $this->bonusCount - 1);
    }

    /**
     * The palette of the screen: 0 is normal, 1 to 8 are the reds of the pain, 9 to 12 the golds of the pickups.
     *
     * @return int<0, 12>
     */
    public function getPalette(): int
    {
        if ($this->damageCount > 0) {
            return \max(0, \min(8, \intdiv($this->damageCount + 7, 8)));
        }

        if ($this->bonusCount > 0) {
            return \max(0, \min(12, 9 + \intdiv($this->bonusCount + 7, 8) - 1));
        }

        return 0;
    }
}
