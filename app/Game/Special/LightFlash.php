<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * A light that flashes at random: it stays bright for 1 or 65 tics and dark for 1 to 8 tics.
 */
final class LightFlash implements Thinker
{
    private readonly int $maxLight;

    private readonly int $minLight;

    private int $count;

    public function __construct(
        private readonly SpecialContext $context,
        private readonly int $sector,
    ) {
        $this->maxLight = $context->sectors->lights[$sector];
        $this->minLight = $context->getMinLightAround($sector, $this->maxLight);
        $this->count = ($context->random->nextByte() & $context->specials->flashBrightMask) + 1;
    }

    public function think(): bool
    {
        if (--$this->count > 0) {
            return true;
        }

        $sectors = $this->context->sectors;

        if ($sectors->lights[$this->sector] === $this->maxLight) {
            $sectors->lights[$this->sector] = $this->minLight;
            $this->count = ($this->context->random->nextByte() & $this->context->specials->flashDarkMask) + 1;

            return true;
        }

        $sectors->lights[$this->sector] = $this->maxLight;
        $this->count = ($this->context->random->nextByte() & $this->context->specials->flashBrightMask) + 1;

        return true;
    }
}
