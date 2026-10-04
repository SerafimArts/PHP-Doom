<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use Serafim\Vulkan\Handle\VkPhysicalDevice;

/**
 * The GPU that the device is created on and the family of the queue that draws.
 */
final readonly class SelectedDevice
{
    public function __construct(
        public VkPhysicalDevice $physical,
        /** @var int<0, 4294967295> */
        public int $queueFamily,
    ) {}
}
