<?php

declare(strict_types=1);

namespace Local\GPU\Raytracing;

use Local\GPU\Resource\Buffer;
use Serafim\Vulkan\Handle\VkAccelerationStructureKHR;

/**
 * A `VkAccelerationStructureKHR` together with the buffer that stores it.
 */
final readonly class AccelerationStructure
{
    public function __construct(
        public VkAccelerationStructureKHR $handle,
        public Buffer $buffer,
        /**
         * Device address that top level instances refer to
         */
        public int $address,
    ) {}
}
