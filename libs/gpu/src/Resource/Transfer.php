<?php

declare(strict_types=1);

namespace Local\GPU\Resource;

use Local\GPU\Device;
use Local\GPU\Exception\DeviceException;
use Local\GPU\Exception\ResourceException;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Sync\ImageBarrier;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkCommandBufferLevel;
use Serafim\Vulkan\Enum\VkCommandBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkCommandPoolCreateFlagBits;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Handle\VkCommandPool;
use Serafim\Vulkan\Handle\VkFence;
use Serafim\Vulkan\Struct\VkBufferCopy;
use Serafim\Vulkan\Struct\VkBufferImageCopy;
use Serafim\Vulkan\Struct\VkCommandBufferAllocateInfo;
use Serafim\Vulkan\Struct\VkCommandBufferBeginInfo;
use Serafim\Vulkan\Struct\VkCommandBufferSubmitInfo;
use Serafim\Vulkan\Struct\VkCommandPoolCreateInfo;
use Serafim\Vulkan\Struct\VkExtent3D;
use Serafim\Vulkan\Struct\VkFenceCreateInfo;
use Serafim\Vulkan\Struct\VkImageSubresourceLayers;
use Serafim\Vulkan\Struct\VkOffset3D;
use Serafim\Vulkan\Struct\VkSubmitInfo2;

/**
 * Immediate transfers between the host and the device: every call submits its commands and waits for them. Meant for
 * loading and for tests, not for the frame loop.
 */
final class Transfer
{
    private readonly VkCommandPool $pool;

    private readonly VkCommandBuffer $commandBuffer;

    private readonly VkFence $fence;

    public function __construct(
        private readonly Device $device,
    ) {
        $vk = $device->vk;

        $this->pool = $vk->vkCreateCommandPool($device->device, new VkCommandPoolCreateInfo(
            flags: VkCommandPoolCreateFlagBits::VK_COMMAND_POOL_CREATE_TRANSIENT_BIT
                | VkCommandPoolCreateFlagBits::VK_COMMAND_POOL_CREATE_RESET_COMMAND_BUFFER_BIT,
            queueFamilyIndex: $device->queueFamily,
        ));

        [$this->commandBuffer] = $vk->vkAllocateCommandBuffers($device->device, new VkCommandBufferAllocateInfo(
            commandPool: $this->pool,
            level: VkCommandBufferLevel::VK_COMMAND_BUFFER_LEVEL_PRIMARY,
            commandBufferCount: 1,
        ));

        $this->fence = $vk->vkCreateFence($device->device, new VkFenceCreateInfo());
    }

    /**
     * @param \Closure(VkCommandBuffer): void $record
     */
    public function submit(\Closure $record): void
    {
        $vk = $this->device->vk;

        $vk->vkResetCommandBuffer($this->commandBuffer);
        $vk->vkBeginCommandBuffer($this->commandBuffer, new VkCommandBufferBeginInfo(
            flags: VkCommandBufferUsageFlagBits::VK_COMMAND_BUFFER_USAGE_ONE_TIME_SUBMIT_BIT,
        ));

        $record($this->commandBuffer);

        $vk->vkEndCommandBuffer($this->commandBuffer);
        $vk->vkQueueSubmit2(
            $this->device->queue,
            [new VkSubmitInfo2(commandBufferInfos: [new VkCommandBufferSubmitInfo($this->commandBuffer)])],
            $this->fence,
        );
        $vk->vkWaitForFences($this->device->device, [$this->fence], true, \PHP_INT_MAX);
        $vk->vkResetFences($this->device->device, [$this->fence]);
    }

    /**
     * @throws ResourceException in case of the data does not fit
     * @throws DeviceException in case of the staging memory is not available
     */
    public function uploadBuffer(Buffer $buffer, string $data, int $offset = 0): void
    {
        if ($data === '') {
            return;
        }

        if ($buffer->isMapped()) {
            $buffer->write($data, $offset);

            return;
        }

        if ($offset + \strlen($data) > $buffer->size) {
            throw ResourceException::becauseDataDoesNotFit($offset + \strlen($data), $buffer->size);
        }

        $staging = $this->createStaging(\strlen($data));
        $staging->write($data);

        $this->submit(fn(VkCommandBuffer $commandBuffer) => $this->device->vk->vkCmdCopyBuffer(
            $commandBuffer,
            $staging->handle,
            $buffer->handle,
            [new VkBufferCopy(0, $offset, \strlen($data))],
        ));
    }

    /**
     * Writes pixels into one layer of an image and leaves the image readable by shaders.
     *
     * @param int<1, max> $bytesPerPixel
     * @param int<0, 4294967295> $layer
     *
     * @throws DeviceException in case of the staging memory is not available
     */
    public function uploadImage(Image $image, string $pixels, int $bytesPerPixel, int $layer = 0): void
    {
        $this->uploadImageRegion(
            $image,
            $pixels,
            $bytesPerPixel,
            0,
            0,
            $image->info->width,
            $image->info->height,
            $layer,
        );
    }

    /**
     * Writes pixels into a rectangle of one layer of an image and leaves the image readable by shaders.
     *
     * @param int<1, max> $bytesPerPixel
     * @param int<0, 2147483647> $x
     * @param int<0, 2147483647> $y
     * @param int<1, 4294967295> $width
     * @param int<1, 4294967295> $height
     * @param int<0, 4294967295> $layer
     *
     * @throws DeviceException in case of the staging memory is not available
     * @throws ResourceException in case of the pixels do not match the rectangle
     */
    public function uploadImageRegion(
        Image $image,
        string $pixels,
        int $bytesPerPixel,
        int $x,
        int $y,
        int $width,
        int $height,
        int $layer = 0,
    ): void {
        if (\strlen($pixels) !== $width * $height * $bytesPerPixel) {
            throw ResourceException::becauseDataDoesNotFit(\strlen($pixels), $width * $height * $bytesPerPixel);
        }

        $staging = $this->createStaging(\strlen($pixels));
        $staging->write($pixels);

        $this->copyBetween(
            $image,
            $staging,
            true,
            $image->layout,
            VkImageLayout::VK_IMAGE_LAYOUT_SHADER_READ_ONLY_OPTIMAL,
            $x,
            $y,
            $width,
            $height,
            $layer,
        );

        $image->layout = VkImageLayout::VK_IMAGE_LAYOUT_SHADER_READ_ONLY_OPTIMAL;
    }

    /**
     * Reads the pixels of the first mip level of one layer of an image, the image keeps its layout.
     *
     * @param int<1, max> $bytesPerPixel
     * @param int<0, 4294967295> $layer
     *
     * @throws DeviceException in case of the staging memory is not available
     */
    public function downloadImage(Image $image, int $bytesPerPixel, int $layer = 0): string
    {
        $size = $image->info->width * $image->info->height * $bytesPerPixel;
        $staging = $this->device->createBuffer(
            $size,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_TRANSFER_DST_BIT,
            MemoryKind::HOST_VISIBLE,
        );

        $original = $image->layout;
        $after = $original;

        if ($original === VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED) {
            $after = null;
        }

        $this->copyBetween(
            $image,
            $staging,
            false,
            $original,
            $after,
            0,
            0,
            $image->info->width,
            $image->info->height,
            $layer,
        );

        return $staging->read($size);
    }

    /**
     * Copies a rectangle of one layer between an image and a buffer and waits: the image goes to the layout of the copy
     * and then to the layout after, or stays in the layout of the copy when there is none.
     *
     * @param bool $isUpload the pixels go from the buffer to the image
     * @param int<-2147483648, 2147483647> $x
     * @param int<-2147483648, 2147483647> $y
     * @param int<0, 4294967295> $width
     * @param int<0, 4294967295> $height
     * @param int<0, 4294967295> $layer
     */
    private function copyBetween(
        Image $image,
        Buffer $buffer,
        bool $isUpload,
        VkImageLayout $before,
        ?VkImageLayout $after,
        int $x,
        int $y,
        int $width,
        int $height,
        int $layer,
    ): void {
        $vk = $this->device->vk;
        $aspect = $image->info->aspect;
        $layers = $image->info->layers;
        $mipLevels = $image->info->mipLevels;

        $copyLayout = VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_SRC_OPTIMAL;

        if ($isUpload) {
            $copyLayout = VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL;
        }

        $this->submit(function (VkCommandBuffer $commandBuffer) use (
            $vk,
            $image,
            $buffer,
            $isUpload,
            $before,
            $after,
            $copyLayout,
            $aspect,
            $layers,
            $mipLevels,
            $x,
            $y,
            $width,
            $height,
            $layer,
        ): void {
            ImageBarrier::transition(
                $vk,
                $commandBuffer,
                $image->handle,
                $before,
                $copyLayout,
                $aspect,
                $layers,
                $mipLevels,
            );

            $region = new VkBufferImageCopy(
                imageSubresource: new VkImageSubresourceLayers($aspect, 0, $layer, 1),
                imageOffset: new VkOffset3D($x, $y, 0),
                imageExtent: new VkExtent3D($width, $height, 1),
            );

            if ($isUpload) {
                $vk->vkCmdCopyBufferToImage($commandBuffer, $buffer->handle, $image->handle, $copyLayout, [$region]);
            } else {
                $vk->vkCmdCopyImageToBuffer($commandBuffer, $image->handle, $copyLayout, $buffer->handle, [$region]);
            }

            if ($after === null) {
                return;
            }

            ImageBarrier::transition(
                $vk,
                $commandBuffer,
                $image->handle,
                $copyLayout,
                $after,
                $aspect,
                $layers,
                $mipLevels,
            );
        });
    }

    /**
     * @param int<1, max> $size
     */
    private function createStaging(int $size): Buffer
    {
        return $this->device->createBuffer(
            $size,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_TRANSFER_SRC_BIT,
            MemoryKind::HOST_VISIBLE,
        );
    }
}
