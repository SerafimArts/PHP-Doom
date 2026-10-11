<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Gpu;

use Local\GPU\Descriptor\DescriptorSet;
use Local\GPU\Resource\Buffer;

/**
 * What one frame in flight writes: the buffers that the host fills before the frame, and the descriptor set that binds
 * them together with the static resources.
 */
final readonly class FrameResources
{
    public function __construct(
        /**
         * The uniform block of the camera and the clock
         */
        public Buffer $frame,
        public Buffer $sectors,
        /**
         * The picture of every wall part
         */
        public Buffer $pictures,
        public Buffer $sprites,
        public Buffer $quads,
        /**
         * The indirect draws of the sprites and the quads
         */
        public Buffer $draw,
        public DescriptorSet $descriptors,
    ) {}
}
