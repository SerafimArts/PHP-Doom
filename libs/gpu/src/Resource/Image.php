<?php

declare(strict_types=1);

namespace Local\GPU\Resource;

use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Handle\VkDeviceMemory;
use Serafim\Vulkan\Handle\VkImage;
use Serafim\Vulkan\Handle\VkImageView;

/**
 * A `VkImage` with its memory and a view over all of its layers.
 */
final class Image
{
    public function __construct(
        public readonly VkImage $handle,
        public readonly VkImageView $view,
        public readonly VkDeviceMemory $memory,
        public readonly ImageInfo $info,
        /**
         * The layout the image is in after the commands recorded so far, kept by whoever transitions it
         */
        public VkImageLayout $layout = VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED,
    ) {}

    public function getFormat(): VkFormat
    {
        return $this->info->format;
    }
}
