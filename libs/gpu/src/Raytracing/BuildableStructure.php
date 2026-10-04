<?php

declare(strict_types=1);

namespace Local\GPU\Raytracing;

use FFI\CData;
use Local\GPU\Resource\Buffer;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Vulkan;

/**
 * An acceleration structure together with the prepared command that builds it from its inputs. The command is recorded
 * as often as the inputs change, for a structure that follows moving things this is every frame.
 */
final readonly class BuildableStructure
{
    public function __construct(
        public AccelerationStructure $structure,
        private Vulkan $vk,
        public Buffer $scratch,
        /**
         * The native description of the build and the ranges of its inputs, they have to outlive the recorded command
         */
        private CData $native,
        public CData $range,
        private CData $ranges,
    ) {}

    public function record(VkCommandBuffer $commandBuffer): void
    {
        $build = $this->vk->vkGetInstanceProc('vkCmdBuildAccelerationStructuresKHR', $commandBuffer);
        $build($commandBuffer->pointer, 1, \FFI::addr($this->native), $this->ranges);
    }
}
