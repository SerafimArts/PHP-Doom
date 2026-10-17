<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * A light that blinks: bright for 5 tics, dark for the given time.
 */
final class StrobeLight implements Thinker
{
    private readonly int $maxLight;

    private readonly int $minLight;

    private int $count;

    /**
     * @param bool $isInSync all the lights of the kind blink at the same moment
     */
    public function __construct(
        private readonly SpecialContext $context,
        private readonly int $sector,
        private readonly int $darkTics,
        bool $isInSync,
    ) {
        $this->maxLight = $context->sectors->lights[$sector];
        $minLight = $context->getMinLightAround($sector, $this->maxLight);

        if ($minLight === $this->maxLight) {
            $minLight = 0;
        }

        $this->minLight = $minLight;
        $this->count = 1;

        if (!$isInSync) {
            $this->count = ($context->random->nextByte() & 7) + 1;
        }
    }

    public function think(): bool
    {
        if (--$this->count > 0) {
            return true;
        }

        $sectors = $this->context->sectors;

        if ($sectors->lights[$this->sector] === $this->minLight) {
            $sectors->lights[$this->sector] = $this->maxLight;
            $this->count = $this->context->specials->strobeBrightTics;

            return true;
        }

        $sectors->lights[$this->sector] = $this->minLight;
        $this->count = $this->darkTics;

        return true;
    }
}
