<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Raytracing;

use Serafim\Doom\Render\Data\FlameProfile;
use Serafim\Doom\Render\Data\LightProfile;
use Serafim\Doom\Render\Data\Limits;
use Serafim\Doom\Render\Scene\SceneState;
use Serafim\Doom\Render\Scene\SceneThing;

/**
 * The lights of a frame: the lamps that stand in the level, the flash of the weapon of the player, the fireballs in
 * flight, the explosions of fireballs and barrels. A light is `float x, y, z, radius, red, green, blue, 0`, a zero
 * radius is no light.
 */
final readonly class LightBuilder
{
    public function __construct(
        private LightProfile $profile,
        private Limits $limits,
    ) {}

    /**
     * @return array{string, int<0, max>} the packed lights and their number
     */
    public function build(SceneState $state, float $time): array
    {
        $lights = [];

        $this->addFlash($state, $lights);

        foreach ($state->things as $thing) {
            $this->addFlame($thing, $this->profile->fireball, $lights);
            $this->addFlame($thing, $this->profile->barrel, $lights);
        }

        $capacity = $this->limits->maxLights - \count($lights);

        foreach ($this->findLamps($state, $capacity) as $index => $lamp) {
            $flicker = $this->profile->flickerBase
                + $this->profile->flickerAmplitude
                * \sin($time * $this->profile->flickerSpeedA + $index * $this->profile->flickerPhase)
                * \sin($time * $this->profile->flickerSpeedB + $index);
            $lights[] = $this->createLight(
                $lamp->x,
                $lamp->y,
                $lamp->z + $this->profile->lampHeight,
                $this->profile->lampRadius,
                $this->profile->lampColor,
                $flicker,
            );
        }

        $lights = \array_slice($lights, 0, $this->limits->maxLights);

        return [\implode('', $lights), \count($lights)];
    }

    /**
     * @param list<string> $lights
     */
    private function addFlame(SceneThing $thing, FlameProfile $flame, array &$lights): void
    {
        $frame = $flame->findFrame($thing->sprite, $thing->frame);

        if ($frame === null) {
            return;
        }

        [$radius, $strength] = $frame;

        $lights[] = $this->createLight(
            $thing->x,
            $thing->y,
            $thing->z + $flame->height,
            $radius,
            $this->profile->fireColor,
            $strength,
        );
    }

    /**
     * @param list<string> $lights
     */
    private function addFlash(SceneState $state, array &$lights): void
    {
        $strength = match ($state->extraLight) {
            2 => $this->profile->flashStrengthFull,
            1 => $this->profile->flashStrengthPartial,
            default => 0.0,
        };

        if ($strength === 0.0 && $state->isFlashing) {
            $strength = $this->profile->flashStrengthShort;
        }

        if ($strength === 0.0) {
            return;
        }

        $cos = \cos($state->yaw);
        $sin = \sin($state->yaw);

        $lights[] = $this->createLight(
            $state->eyeX + $cos * $this->profile->flashForward + $sin * $this->profile->flashSide,
            $state->eyeY + $sin * $this->profile->flashForward - $cos * $this->profile->flashSide,
            $state->eyeZ + $this->profile->flashUp,
            $this->profile->flashRadius,
            $this->profile->flashColor,
            $strength,
        );
    }

    /**
     * The lamps that are nearest to the player when there are more than the lights can hold.
     *
     * @return list<SceneThing>
     */
    private function findLamps(SceneState $state, int $capacity): array
    {
        $lamps = [];

        foreach ($state->things as $thing) {
            if ($thing->type === $this->profile->lampThing) {
                $lamps[] = $thing;
            }
        }

        if (\count($lamps) > $capacity) {
            \usort(
                $lamps,
                static fn(SceneThing $a, SceneThing $b): int => self::getDistanceToPlayer($a, $state)
                    <=> self::getDistanceToPlayer($b, $state),
            );
        }

        return \array_slice($lamps, 0, \max(0, $capacity));
    }

    private static function getDistanceToPlayer(SceneThing $thing, SceneState $state): float
    {
        return ($thing->x - $state->playerX) ** 2 + ($thing->y - $state->playerY) ** 2;
    }

    /**
     * @param array{float, float, float} $color
     */
    private function createLight(float $x, float $y, float $z, float $radius, array $color, float $strength): string
    {
        return \pack(
            'g8',
            $x,
            $y,
            $z,
            $radius,
            $color[0] * $strength,
            $color[1] * $strength,
            $color[2] * $strength,
            0.0,
        );
    }
}
