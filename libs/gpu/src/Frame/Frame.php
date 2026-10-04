<?php

declare(strict_types=1);

namespace Local\GPU\Frame;

/**
 * A frame that is being prepared: write the data of the frame index, then pass the frame to {@see
 * FrameLoop::endFrame()}.
 */
final readonly class Frame
{
    public function __construct(
        /**
         * Index of the frame in flight
         *
         * @var int<0, 4294967295>
         */
        public int $index,
        /**
         * @internal this is an internal library property, please do not use it in your code
         * @psalm-internal Local\GPU
         *
         * @var int<0, 4294967295>
         */
        public int $imageIndex,
    ) {}
}
