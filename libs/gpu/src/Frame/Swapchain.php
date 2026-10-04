<?php

declare(strict_types=1);

namespace Local\GPU\Frame;

use Local\GPU\Device;
use Local\GPU\Exception\DeviceException;
use Serafim\Vulkan\Enum\VkColorSpaceKHR;
use Serafim\Vulkan\Enum\VkCompositeAlphaFlagBitsKHR;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageAspectFlagBits;
use Serafim\Vulkan\Enum\VkImageUsageFlagBits;
use Serafim\Vulkan\Enum\VkImageViewType;
use Serafim\Vulkan\Enum\VkPresentModeKHR;
use Serafim\Vulkan\Handle\VkImage;
use Serafim\Vulkan\Handle\VkImageView;
use Serafim\Vulkan\Handle\VkSemaphore;
use Serafim\Vulkan\Handle\VkSwapchainKHR;
use Serafim\Vulkan\Struct\VkComponentMapping;
use Serafim\Vulkan\Struct\VkExtent2D;
use Serafim\Vulkan\Struct\VkImageSubresourceRange;
use Serafim\Vulkan\Struct\VkImageViewCreateInfo;
use Serafim\Vulkan\Struct\VkPhysicalDeviceSurfaceInfo2KHR;
use Serafim\Vulkan\Struct\VkSemaphoreCreateInfo;
use Serafim\Vulkan\Struct\VkSwapchainCreateInfoKHR;

/**
 * The images a window presents. Images can be used as color attachments and as targets of a transfer (blit).
 */
final readonly class Swapchain
{
    private function __construct(
        public VkSwapchainKHR $handle,
        public VkFormat $format,
        public VkExtent2D $extent,
        /** @var list<VkImage> */
        public array $images,
        /** @var list<VkImageView> */
        public array $views,
        /**
         * One semaphore per image, signaled when the image is ready to present
         *
         * @var list<VkSemaphore>
         */
        public array $renderFinished,
    ) {}

    /**
     * Creates a swapchain of the size of the framebuffer, or returns {@see null} while the window is minimized.
     *
     * @throws DeviceException in case of the device has no window
     */
    public static function create(Device $device, bool $isVsyncEnabled, ?self $previous = null): ?self
    {
        $window = $device->window ?? throw DeviceException::becauseWindowRequired();
        $surface = $device->surface ?? throw DeviceException::becauseWindowRequired();
        $vk = $device->vk;

        $capabilities = $vk->vkGetPhysicalDeviceSurfaceCapabilities2KHR(
            $device->physicalDevice,
            new VkPhysicalDeviceSurfaceInfo2KHR(surface: $surface),
        )->surfaceCapabilities;
        [$width, $height] = $window->getFramebufferSize();

        $extent = new VkExtent2D(
            \max($capabilities->minImageExtent->width, \min($capabilities->maxImageExtent->width, $width)),
            \max($capabilities->minImageExtent->height, \min($capabilities->maxImageExtent->height, $height)),
        );

        if ($extent->width === 0 || $extent->height === 0) {
            return null;
        }

        $format = self::chooseFormat($device);

        $handle = $vk->vkCreateSwapchainKHR($device->device, new VkSwapchainCreateInfoKHR(
            surface: $surface,
            imageExtent: $extent,
            minImageCount: \max($capabilities->minImageCount, 3),
            imageFormat: $format,
            imageColorSpace: VkColorSpaceKHR::VK_COLOR_SPACE_SRGB_NONLINEAR_KHR,
            imageArrayLayers: 1,
            imageUsage: VkImageUsageFlagBits::VK_IMAGE_USAGE_COLOR_ATTACHMENT_BIT
                | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_DST_BIT,
            preTransform: $capabilities->currentTransform,
            compositeAlpha: VkCompositeAlphaFlagBitsKHR::VK_COMPOSITE_ALPHA_OPAQUE_BIT_KHR,
            presentMode: self::choosePresentMode($device, $isVsyncEnabled),
            clipped: true,
            oldSwapchain: $previous?->handle,
        ));

        $images = [];
        $views = [];
        $semaphores = [];

        foreach ($vk->vkGetSwapchainImagesKHR($device->device, $handle) as $image) {
            $images[] = $image;
            $semaphores[] = $vk->vkCreateSemaphore($device->device, new VkSemaphoreCreateInfo());
            $views[] = $vk->vkCreateImageView($device->device, new VkImageViewCreateInfo(
                image: $image,
                components: new VkComponentMapping(),
                subresourceRange: new VkImageSubresourceRange(
                    VkImageAspectFlagBits::VK_IMAGE_ASPECT_COLOR_BIT,
                    0,
                    1,
                    0,
                    1,
                ),
                viewType: VkImageViewType::VK_IMAGE_VIEW_TYPE_2D,
                format: $format,
            ));
        }

        return new self($handle, $format, $extent, $images, $views, $semaphores);
    }

    private static function chooseFormat(Device $device): VkFormat
    {
        $surface = $device->surface ?? throw DeviceException::becauseWindowRequired();
        $first = null;

        foreach ($device->vk->vkGetPhysicalDeviceSurfaceFormats2KHR(
            $device->physicalDevice,
            new VkPhysicalDeviceSurfaceInfo2KHR(surface: $surface),
        ) as $candidate) {
            $format = $candidate->surfaceFormat->format;

            if ($format === VkFormat::VK_FORMAT_B8G8R8A8_UNORM) {
                return $format;
            }

            $first ??= $format;
        }

        return $first ?? VkFormat::VK_FORMAT_B8G8R8A8_UNORM;
    }

    private static function choosePresentMode(Device $device, bool $isVsyncEnabled): VkPresentModeKHR
    {
        if ($isVsyncEnabled) {
            return VkPresentModeKHR::VK_PRESENT_MODE_FIFO_KHR;
        }

        $surface = $device->surface ?? throw DeviceException::becauseWindowRequired();
        $modes = $device->vk->vkGetPhysicalDeviceSurfacePresentModesKHR($device->physicalDevice, $surface);

        $preferredModes = [
            VkPresentModeKHR::VK_PRESENT_MODE_MAILBOX_KHR,
            VkPresentModeKHR::VK_PRESENT_MODE_IMMEDIATE_KHR,
        ];

        foreach ($preferredModes as $mode) {
            if (\in_array($mode, $modes, true)) {
                return $mode;
            }
        }

        return VkPresentModeKHR::VK_PRESENT_MODE_FIFO_KHR;
    }
}
