<?php

declare(strict_types=1);

namespace Local\GPU\Raytracing;

use Serafim\Vulkan\Enum\VkAccessFlagBits2;
use Serafim\Vulkan\Enum\VkPipelineStageFlagBits2;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Struct\VkDependencyInfo;
use Serafim\Vulkan\Struct\VkMemoryBarrier2;
use Serafim\Vulkan\Vulkan;

/**
 * Orders the builds of acceleration structures against what reads them.
 */
final readonly class AccelerationStructureBarrier
{
    /**
     * A top level build reads the bottom level structures built before it.
     */
    public static function betweenBuilds(Vulkan $vk, VkCommandBuffer $commandBuffer): void
    {
        $vk->vkCmdPipelineBarrier2($commandBuffer, new VkDependencyInfo(memoryBarriers: [new VkMemoryBarrier2(
            srcStageMask: VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ACCELERATION_STRUCTURE_BUILD_BIT_KHR,
            srcAccessMask: VkAccessFlagBits2::VK_ACCESS_2_ACCELERATION_STRUCTURE_WRITE_BIT_KHR,
            dstStageMask: VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ACCELERATION_STRUCTURE_BUILD_BIT_KHR,
            dstAccessMask: VkAccessFlagBits2::VK_ACCESS_2_ACCELERATION_STRUCTURE_READ_BIT_KHR
                | VkAccessFlagBits2::VK_ACCESS_2_ACCELERATION_STRUCTURE_WRITE_BIT_KHR,
        )]));
    }

    /**
     * The shaders of the given stages trace rays against what was built.
     *
     * @param int<0, max> $shaderStages stages of `VkPipelineStageFlagBits2`
     */
    public static function beforeShaders(Vulkan $vk, VkCommandBuffer $commandBuffer, int $shaderStages): void
    {
        $vk->vkCmdPipelineBarrier2($commandBuffer, new VkDependencyInfo(memoryBarriers: [new VkMemoryBarrier2(
            srcStageMask: VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ACCELERATION_STRUCTURE_BUILD_BIT_KHR,
            srcAccessMask: VkAccessFlagBits2::VK_ACCESS_2_ACCELERATION_STRUCTURE_WRITE_BIT_KHR,
            dstStageMask: $shaderStages,
            dstAccessMask: VkAccessFlagBits2::VK_ACCESS_2_ACCELERATION_STRUCTURE_READ_BIT_KHR,
        )]));
    }
}
