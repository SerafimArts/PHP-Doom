<?php

declare(strict_types=1);

namespace Local\GPU\Sync;

use Serafim\Vulkan\Enum\VkAccessFlagBits2;
use Serafim\Vulkan\Enum\VkImageAspectFlagBits;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Enum\VkPipelineStageFlagBits2;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Handle\VkImage;
use Serafim\Vulkan\Struct\VkDependencyInfo;
use Serafim\Vulkan\Struct\VkImageMemoryBarrier2;
use Serafim\Vulkan\Struct\VkImageSubresourceRange;
use Serafim\Vulkan\Vulkan;

use const Serafim\Vulkan\VK_QUEUE_FAMILY_IGNORED;

/**
 * Layout transitions of images with the stages and accesses that belong to each layout.
 */
final class ImageBarrier
{
    private function __construct() {}

    /**
     * @param int<0, 2047> $aspect aspect flags of `VkImageAspectFlagBits`
     * @param int<1, 4294967295> $layers
     * @param int<1, 4294967295> $mipLevels
     */
    public static function transition(
        Vulkan $vk,
        VkCommandBuffer $commandBuffer,
        VkImage $image,
        VkImageLayout $from,
        VkImageLayout $to,
        int $aspect = VkImageAspectFlagBits::VK_IMAGE_ASPECT_COLOR_BIT,
        int $layers = 1,
        int $mipLevels = 1,
    ): void {
        [$sourceStage, $sourceAccess] = self::describe($from);
        [$targetStage, $targetAccess] = self::describe($to);

        $vk->vkCmdPipelineBarrier2($commandBuffer, new VkDependencyInfo(imageMemoryBarriers: [
            new VkImageMemoryBarrier2(
                image: $image,
                subresourceRange: new VkImageSubresourceRange($aspect, 0, $mipLevels, 0, $layers),
                srcStageMask: $sourceStage,
                srcAccessMask: $sourceAccess,
                dstStageMask: $targetStage,
                dstAccessMask: $targetAccess,
                oldLayout: $from,
                newLayout: $to,
                srcQueueFamilyIndex: VK_QUEUE_FAMILY_IGNORED,
                dstQueueFamilyIndex: VK_QUEUE_FAMILY_IGNORED,
            ),
        ]));
    }

    /**
     * @return array{int, int} the pipeline stages and the accesses that use the image in the layout
     */
    private static function describe(VkImageLayout $layout): array
    {
        return match ($layout) {
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL => [
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ALL_TRANSFER_BIT,
                VkAccessFlagBits2::VK_ACCESS_2_TRANSFER_WRITE_BIT,
            ],
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_SRC_OPTIMAL => [
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ALL_TRANSFER_BIT,
                VkAccessFlagBits2::VK_ACCESS_2_TRANSFER_READ_BIT,
            ],
            VkImageLayout::VK_IMAGE_LAYOUT_SHADER_READ_ONLY_OPTIMAL => [
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_VERTEX_SHADER_BIT
                    | VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_FRAGMENT_SHADER_BIT
                    | VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_COMPUTE_SHADER_BIT,
                VkAccessFlagBits2::VK_ACCESS_2_SHADER_SAMPLED_READ_BIT,
            ],
            VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL => [
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_COLOR_ATTACHMENT_OUTPUT_BIT,
                VkAccessFlagBits2::VK_ACCESS_2_COLOR_ATTACHMENT_READ_BIT
                    | VkAccessFlagBits2::VK_ACCESS_2_COLOR_ATTACHMENT_WRITE_BIT,
            ],
            VkImageLayout::VK_IMAGE_LAYOUT_DEPTH_STENCIL_ATTACHMENT_OPTIMAL => [
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_EARLY_FRAGMENT_TESTS_BIT
                    | VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_LATE_FRAGMENT_TESTS_BIT,
                VkAccessFlagBits2::VK_ACCESS_2_DEPTH_STENCIL_ATTACHMENT_READ_BIT
                    | VkAccessFlagBits2::VK_ACCESS_2_DEPTH_STENCIL_ATTACHMENT_WRITE_BIT,
            ],
            VkImageLayout::VK_IMAGE_LAYOUT_PRESENT_SRC_KHR => [
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_BOTTOM_OF_PIPE_BIT,
                0,
            ],
            VkImageLayout::VK_IMAGE_LAYOUT_GENERAL => [
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ALL_COMMANDS_BIT,
                VkAccessFlagBits2::VK_ACCESS_2_MEMORY_READ_BIT | VkAccessFlagBits2::VK_ACCESS_2_MEMORY_WRITE_BIT,
            ],
            default => [VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_TOP_OF_PIPE_BIT, 0],
        };
    }
}
