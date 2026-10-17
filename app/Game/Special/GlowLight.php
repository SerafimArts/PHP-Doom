<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * A light that fades down to the darkest light around and up to its own light again, 8 levels per tic.
 */
final class GlowLight implements Thinker
{
    private readonly int $maxLight;

    private readonly int $minLight;

    private int $direction = -1;

    public function __construct(
        private readonly SpecialContext $context,
        private readonly int $sector,
    ) {
        $this->maxLight = $context->sectors->lights[$sector];
        $this->minLight = $context->getMinLightAround($sector, $this->maxLight);
    }

    public function think(): bool
    {
        $sectors = $this->context->sectors;
        $light = $sectors->lights[$this->sector] + $this->direction * $this->context->specials->glowSpeed;

        if ($this->direction < 0 && $light <= $this->minLight) {
            $light += $this->context->specials->glowSpeed;
            $this->direction = 1;
        } elseif ($this->direction > 0 && $light >= $this->maxLight) {
            $light -= $this->context->specials->glowSpeed;
            $this->direction = -1;
        }

        $sectors->lights[$this->sector] = $light;

        return true;
    }
}
