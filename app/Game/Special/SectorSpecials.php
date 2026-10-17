<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerStats;
use Serafim\Doom\Level\SectorSpecial;

/**
 * The special of a sector: lights that change by themselves, damage of the floor and secrets.
 */
final class SectorSpecials
{
    /**
     * How often a floor hurts: every 32 tics
     */
    private const int DAMAGE_MASK = 0x1F;

    /**
     * Secrets of the level
     *
     * @var int<0, max>
     */
    public int $totalSecrets = 0;

    public function __construct(
        private readonly SpecialContext $context,
    ) {}

    /**
     * Starts the lights of the level and counts its secrets.
     */
    public function spawn(): void
    {
        $sectors = $this->context->sectors;

        foreach ($sectors->specials as $index => $special) {
            $kind = SectorSpecial::tryFrom($special);
            $light = match ($kind) {
                SectorSpecial::LIGHT_BLINK_RANDOM => new LightFlash($this->context, $index),
                SectorSpecial::LIGHT_GLOW => new GlowLight($this->context, $index),
                SectorSpecial::STROBE_SLOW_SYNC => new StrobeLight(
                    $this->context,
                    $index,
                    $this->context->specials->strobeSlowDarkTics,
                    true,
                ),
                default => null,
            };

            if ($light !== null) {
                $this->context->thinkers->add($light);
                $sectors->specials[$index] = 0;
            }

            if ($kind === SectorSpecial::SECRET) {
                ++$this->totalSecrets;
            }
        }
    }

    public function applyToPlayer(Player $player, PlayerStats $stats, int $tic): void
    {
        $sectors = $this->context->sectors;
        $sector = $this->context->level->findSectorAt($player->x, $player->y);
        $special = SectorSpecial::tryFrom($sectors->specials[$sector]);

        if ($special === null || \abs($player->z - $sectors->floorHeights[$sector]) > 1.0E-6) {
            return;
        }

        if ($special === SectorSpecial::SECRET) {
            ++$stats->secrets;
            $sectors->specials[$sector] = 0;

            return;
        }

        $damage = $this->context->specials->getSectorDamage($special);

        if ($damage > 0 && ($tic & self::DAMAGE_MASK) === 0) {
            $stats->damage($damage);
        }
    }
}
