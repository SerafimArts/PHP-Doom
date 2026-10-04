<?php

declare(strict_types=1);

namespace Local\GPU\Frame;

use Local\GPU\Device;
use Local\GPU\Exception\DeviceException;
use Serafim\Vulkan\Enum\VkCommandBufferLevel;
use Serafim\Vulkan\Enum\VkFenceCreateFlagBits;
use Serafim\Vulkan\Enum\VkPipelineStageFlagBits2;
use Serafim\Vulkan\Enum\VkResult;
use Serafim\Vulkan\Exception\CommandFailedException;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Handle\VkFence;
use Serafim\Vulkan\Handle\VkSemaphore;
use Serafim\Vulkan\Struct\VkCommandBufferAllocateInfo;
use Serafim\Vulkan\Struct\VkCommandBufferBeginInfo;
use Serafim\Vulkan\Struct\VkCommandBufferSubmitInfo;
use Serafim\Vulkan\Struct\VkCommandPoolCreateInfo;
use Serafim\Vulkan\Struct\VkFenceCreateInfo;
use Serafim\Vulkan\Struct\VkPresentInfoKHR;
use Serafim\Vulkan\Struct\VkSemaphoreCreateInfo;
use Serafim\Vulkan\Struct\VkSemaphoreSubmitInfo;
use Serafim\Vulkan\Struct\VkSubmitInfo2;

/**
 * The frame cycle of a window: acquire an image, submit, present, and recreate the swapchain when the window changes.
 *
 * The commands of a frame are recorded once for every pair of a frame in flight and a swapchain image (and again after
 * the swapchain is recreated), so a frame costs the host a few calls. Everything that changes between the frames lives
 * in buffers that the commands read, one set of buffers per frame in flight, see {@see Frame::$index}.
 */
final class FrameLoop
{
    public const int FRAMES_IN_FLIGHT = 2;

    private ?Swapchain $swapchain = null;

    private bool $isOutdated = true;

    /**
     * Command buffers by frame in flight and swapchain image.
     *
     * @var list<list<VkCommandBuffer>>
     */
    private array $commandBuffers = [];

    /** @var list<VkSemaphore> */
    private readonly array $acquired;

    /** @var list<VkFence> */
    private readonly array $fences;

    /** @var int<0, 4294967295> */
    private int $frame = 0;

    public function __construct(
        private readonly Device $device,
        /**
         * Records the commands that draw into the target image
         *
         * @var \Closure(FrameTarget): void
         */
        private readonly \Closure $recorder,
        private readonly bool $isVsyncEnabled = true,
    ) {
        $acquired = [];
        $fences = [];

        for ($i = 0; $i < self::FRAMES_IN_FLIGHT; ++$i) {
            $acquired[] = $device->vk->vkCreateSemaphore($device->device, new VkSemaphoreCreateInfo());
            $fences[] = $device->vk->vkCreateFence($device->device, new VkFenceCreateInfo(
                flags: VkFenceCreateFlagBits::VK_FENCE_CREATE_SIGNALED_BIT,
            ));
        }

        $this->acquired = $acquired;
        $this->fences = $fences;
    }

    /**
     * Size of the images that are presented now, {@see null} while the window is minimized.
     *
     * @return array{int<1, 4294967295>, int<1, 4294967295>}|null
     */
    public function getExtent(): ?array
    {
        if ($this->swapchain === null) {
            return null;
        }

        $width = $this->swapchain->extent->width;
        $height = $this->swapchain->extent->height;
        \assert($width > 0 && $height > 0);

        return [$width, $height];
    }

    /**
     * Waits for the frame in flight and acquires the image. Returns {@see null} when there is nothing to draw (the
     * window is minimized or the swapchain had to be recreated), call it again on the next iteration.
     *
     * @throws DeviceException in case of the device has no window
     */
    public function beginFrame(): ?Frame
    {
        $window = $this->device->window ?? throw DeviceException::becauseWindowRequired();
        $vk = $this->device->vk;

        if ($window->consumeResize()) {
            $this->isOutdated = true;
        }

        if ($this->isOutdated && !$this->recreate()) {
            return null;
        }

        $swapchain = $this->swapchain;

        if ($swapchain === null) {
            return null;
        }

        $fence = $this->fences[$this->frame];
        $vk->vkWaitForFences($this->device->device, [$fence], true, \PHP_INT_MAX);

        try {
            $acquisition = $vk->vkAcquireNextImageKHR(
                $this->device->device,
                $swapchain->handle,
                \PHP_INT_MAX,
                $this->acquired[$this->frame],
            );
        } catch (CommandFailedException $e) {
            if ($e->getCode() !== VkResult::VK_ERROR_OUT_OF_DATE_KHR->value) {
                throw $e;
            }

            $this->isOutdated = true;

            return null;
        }

        if ($acquisition->status === VkResult::VK_SUBOPTIMAL_KHR) {
            $this->isOutdated = true;
        }

        $vk->vkResetFences($this->device->device, [$fence]);

        $imageIndex = $acquisition->value;
        \assert($imageIndex >= 0 && $imageIndex <= 4_294_967_295);

        return new Frame($this->frame, $imageIndex);
    }

    public function endFrame(Frame $frame): void
    {
        $swapchain = $this->swapchain;

        if ($swapchain === null) {
            return;
        }

        $vk = $this->device->vk;
        $finished = $swapchain->renderFinished[$frame->imageIndex];

        $vk->vkQueueSubmit2($this->device->queue, [new VkSubmitInfo2(
            waitSemaphoreInfos: [new VkSemaphoreSubmitInfo(
                $this->acquired[$frame->index],
                0,
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ALL_COMMANDS_BIT,
            )],
            commandBufferInfos: [
                new VkCommandBufferSubmitInfo($this->commandBuffers[$frame->index][$frame->imageIndex]),
            ],
            signalSemaphoreInfos: [new VkSemaphoreSubmitInfo(
                $finished,
                0,
                VkPipelineStageFlagBits2::VK_PIPELINE_STAGE_2_ALL_COMMANDS_BIT,
            )],
        )], $this->fences[$frame->index]);

        try {
            $status = $vk->vkQueuePresentKHR($this->device->queue, new VkPresentInfoKHR(
                waitSemaphores: [$finished],
                swapchains: [$swapchain->handle],
                imageIndices: [$frame->imageIndex],
            ));

            if ($status === VkResult::VK_SUBOPTIMAL_KHR) {
                $this->isOutdated = true;
            }
        } catch (CommandFailedException $e) {
            if ($e->getCode() !== VkResult::VK_ERROR_OUT_OF_DATE_KHR->value) {
                throw $e;
            }

            $this->isOutdated = true;
        }

        $this->frame = ($this->frame + 1) % self::FRAMES_IN_FLIGHT;
    }

    public function waitIdle(): void
    {
        $this->device->waitIdle();
    }

    private function recreate(): bool
    {
        $this->device->waitIdle();

        $swapchain = Swapchain::create($this->device, $this->isVsyncEnabled, $this->swapchain);
        $this->swapchain = $swapchain;

        if ($swapchain === null) {
            $this->commandBuffers = [];

            return false;
        }

        $this->record($swapchain);
        $this->isOutdated = false;

        return true;
    }

    private function record(Swapchain $swapchain): void
    {
        $vk = $this->device->vk;
        $images = \min(4_294_967_295, \count($swapchain->images));

        $pool = $vk->vkCreateCommandPool($this->device->device, new VkCommandPoolCreateInfo(
            queueFamilyIndex: $this->device->queueFamily,
        ));

        $commandBuffers = [];

        for ($frame = 0; $frame < self::FRAMES_IN_FLIGHT; ++$frame) {
            $allocated = $vk->vkAllocateCommandBuffers($this->device->device, new VkCommandBufferAllocateInfo(
                commandPool: $pool,
                level: VkCommandBufferLevel::VK_COMMAND_BUFFER_LEVEL_PRIMARY,
                commandBufferCount: $images,
            ));

            $commandBuffers[] = $allocated;

            foreach ($allocated as $image => $commandBuffer) {
                $vk->vkBeginCommandBuffer($commandBuffer, new VkCommandBufferBeginInfo());

                \assert($image >= 0 && $image <= 4_294_967_295);

                ($this->recorder)(new FrameTarget(
                    commandBuffer: $commandBuffer,
                    image: $swapchain->images[$image],
                    view: $swapchain->views[$image],
                    format: $swapchain->format,
                    extent: $swapchain->extent,
                    frameIndex: $frame,
                    imageIndex: $image,
                ));

                $vk->vkEndCommandBuffer($commandBuffer);
            }
        }

        $this->commandBuffers = $commandBuffers;
    }
}
