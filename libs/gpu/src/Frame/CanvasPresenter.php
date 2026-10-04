<?php

declare(strict_types=1);

namespace Local\GPU\Frame;

use Local\GPU\Resource\Image;
use Local\GPU\Sync\ImageBarrier;
use Serafim\Vulkan\Enum\VkFilter;
use Serafim\Vulkan\Enum\VkImageAspectFlagBits;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Struct\VkClearColorValue;
use Serafim\Vulkan\Struct\VkImageBlit;
use Serafim\Vulkan\Struct\VkImageSubresourceLayers;
use Serafim\Vulkan\Struct\VkImageSubresourceRange;
use Serafim\Vulkan\Struct\VkOffset3D;
use Serafim\Vulkan\Vulkan;

/**
 * Shows a canvas of a fixed size on the swapchain image: scaled with the nearest neighbour to the largest rectangle of
 * the given proportion, black bars around it. The proportion of the picture on the screen does not have to be the
 * proportion of the canvas.
 */
final class CanvasPresenter
{
    private const int MAX_OFFSET = 2_147_483_647;

    private function __construct() {}

    /**
     * @return int<0, 2147483647>
     */
    private static function clampToOffset(int $value): int
    {
        return \max(0, \min(self::MAX_OFFSET, $value));
    }

    /**
     * Records the transfer of the canvas to the target and leaves the swapchain image ready to present.
     *
     * @param VkImageLayout $canvasLayout the layout the canvas is in when the commands run
     * @param float $proportion width divided by height of the picture on the screen
     */
    public static function record(
        Vulkan $vk,
        FrameTarget $target,
        Image $canvas,
        VkImageLayout $canvasLayout = VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL,
        float $proportion = 4 / 3,
    ): void {
        $commandBuffer = $target->commandBuffer;
        $width = $target->extent->width;
        $height = $target->extent->height;

        $shownWidth = \max(1, \min($width, (int) ($height * $proportion)));
        $shownHeight = \max(1, \min($height, (int) ($width / $proportion)));
        $left = \intdiv($width - $shownWidth, 2);
        $top = \intdiv($height - $shownHeight, 2);
        $right = self::clampToOffset($left + $shownWidth);
        $bottom = self::clampToOffset($top + $shownHeight);
        $left = self::clampToOffset($left);
        $top = self::clampToOffset($top);
        $sourceWidth = self::clampToOffset($canvas->info->width);
        $sourceHeight = self::clampToOffset($canvas->info->height);

        $color = VkImageAspectFlagBits::VK_IMAGE_ASPECT_COLOR_BIT;

        ImageBarrier::transition(
            $vk,
            $commandBuffer,
            $canvas->handle,
            $canvasLayout,
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_SRC_OPTIMAL,
        );
        ImageBarrier::transition(
            $vk,
            $commandBuffer,
            $target->image,
            VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED,
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL,
        );

        $vk->vkCmdClearColorImage(
            $commandBuffer,
            $target->image,
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL,
            new VkClearColorValue(float32: [0.0, 0.0, 0.0, 1.0]),
            [new VkImageSubresourceRange($color, 0, 1, 0, 1)],
        );

        $vk->vkCmdBlitImage(
            $commandBuffer,
            $canvas->handle,
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_SRC_OPTIMAL,
            $target->image,
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL,
            [new VkImageBlit(
                new VkImageSubresourceLayers($color, 0, 0, 1),
                [new VkOffset3D(0, 0, 0), new VkOffset3D($sourceWidth, $sourceHeight, 1)],
                new VkImageSubresourceLayers($color, 0, 0, 1),
                [new VkOffset3D($left, $top, 0), new VkOffset3D($right, $bottom, 1)],
            )],
            VkFilter::VK_FILTER_NEAREST,
        );

        ImageBarrier::transition(
            $vk,
            $commandBuffer,
            $target->image,
            VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL,
            VkImageLayout::VK_IMAGE_LAYOUT_PRESENT_SRC_KHR,
        );
    }
}
