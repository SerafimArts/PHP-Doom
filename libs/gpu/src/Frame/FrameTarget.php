<?php

declare(strict_types=1);

namespace Local\GPU\Frame;

use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Handle\VkImage;
use Serafim\Vulkan\Handle\VkImageView;
use Serafim\Vulkan\Struct\VkExtent2D;

/**
 * What a recorder gets to record the commands of one frame: the command buffer and the swapchain image it presents.
 * The image is in the layout `UNDEFINED` and has to be in `PRESENT_SRC_KHR` when the commands end.
 */
final readonly class FrameTarget
{
    public function __construct(
        public VkCommandBuffer $commandBuffer,
        public VkImage $image,
        public VkImageView $view,
        public VkFormat $format,
        public VkExtent2D $extent,
        /**
         * Index of the frame in flight, the data buffers a frame reads are chosen by it
         *
         * @var int<0, 4294967295>
         */
        public int $frameIndex,
        /** @var int<0, 4294967295> */
        public int $imageIndex,
    ) {}
}
