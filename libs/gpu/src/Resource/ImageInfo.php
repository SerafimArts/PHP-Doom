<?php

declare(strict_types=1);

namespace Local\GPU\Resource;

use Local\GPU\Memory\MemoryKind;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageAspectFlagBits;
use Serafim\Vulkan\Enum\VkImageViewType;

final readonly class ImageInfo
{
    public function __construct(
        /** @var int<1, 4294967295> */
        public int $width,
        /** @var int<1, 4294967295> */
        public int $height,
        public VkFormat $format,
        /**
         * Usage flags of `VkImageUsageFlagBits`
         *
         * @var int<0, 4294967295>
         */
        public int $usage,
        /**
         * More than one layer makes a 2D array image and view
         *
         * @var int<1, 4294967295>
         */
        public int $layers = 1,
        /** @var int<1, 4294967295> */
        public int $mipLevels = 1,
        /**
         * Aspect of the view, {@see VkImageAspectFlagBits}
         *
         * @var int<0, 2047>
         */
        public int $aspect = VkImageAspectFlagBits::VK_IMAGE_ASPECT_COLOR_BIT,
        public MemoryKind $kind = MemoryKind::DEVICE_LOCAL,
    ) {}

    /**
     * A 2D array view when the image has more than one layer.
     */
    public function getViewType(): VkImageViewType
    {
        if ($this->layers > 1) {
            return VkImageViewType::VK_IMAGE_VIEW_TYPE_2D_ARRAY;
        }

        return VkImageViewType::VK_IMAGE_VIEW_TYPE_2D;
    }
}
