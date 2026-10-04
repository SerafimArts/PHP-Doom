<?php

declare(strict_types=1);

namespace Local\GPU\Tests\Support;

use Local\GPU\Descriptor\DescriptorLayout;
use Local\GPU\Descriptor\DescriptorSet;
use Local\GPU\Device;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Pipeline\GraphicsPipelineInfo;
use Local\GPU\Pipeline\Pipeline;
use Local\GPU\Resource\Buffer;
use Local\GPU\Resource\Image;
use Local\GPU\Resource\ImageInfo;
use Local\GPU\Resource\Transfer;
use Local\GPU\Sync\ImageBarrier;
use Serafim\Vulkan\Enum\VkAttachmentLoadOp;
use Serafim\Vulkan\Enum\VkAttachmentStoreOp;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkDescriptorType;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Enum\VkImageUsageFlagBits;
use Serafim\Vulkan\Enum\VkPipelineBindPoint;
use Serafim\Vulkan\Enum\VkShaderStageFlagBits;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Struct\VkClearColorValue;
use Serafim\Vulkan\Struct\VkClearValue;
use Serafim\Vulkan\Struct\VkExtent2D;
use Serafim\Vulkan\Struct\VkOffset2D;
use Serafim\Vulkan\Struct\VkRect2D;
use Serafim\Vulkan\Struct\VkRenderingAttachmentInfo;
use Serafim\Vulkan\Struct\VkRenderingInfo;
use Serafim\Vulkan\Struct\VkViewport;

/**
 * Colored quads drawn from an indexed-color atlas and a palette into an offscreen image: instances in a storage buffer,
 * two sampled images, one instanced draw. Tile `t` of the 4x4 atlas holds the palette index `t * 16 + 1`, the palette
 * color `i` is `(i, 0, 255 - i, 255)`.
 */
final class QuadsScene
{
    public const int SIZE = 64;

    public const int INSTANCE_SIZE = 32;

    private readonly Buffer $instances;

    private readonly DescriptorSet $descriptors;

    private readonly Pipeline $pipeline;

    private readonly Image $target;

    public function __construct(
        private readonly Device $device,
        private readonly Transfer $transfer,
        string $shaders,
    ) {
        $atlas = $device->createImage(new ImageInfo(
            16 * 4,
            16 * 4,
            VkFormat::VK_FORMAT_R8_UNORM,
            VkImageUsageFlagBits::VK_IMAGE_USAGE_SAMPLED_BIT | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_DST_BIT,
        ));

        $pixels = '';

        for ($y = 0; $y < 64; ++$y) {
            for ($x = 0; $x < 64; ++$x) {
                $pixels .= \chr(\intdiv($y, 16) * 4 * 16 + \intdiv($x, 16) * 16 + 1);
            }
        }

        $transfer->uploadImage($atlas, $pixels, 1);

        $palette = $device->createImage(new ImageInfo(
            256,
            1,
            VkFormat::VK_FORMAT_R8G8B8A8_UNORM,
            VkImageUsageFlagBits::VK_IMAGE_USAGE_SAMPLED_BIT | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_DST_BIT,
        ));

        $colors = '';

        for ($i = 0; $i < 256; ++$i) {
            $colors .= \chr($i) . "\0" . \chr(255 - $i) . "\xFF";
        }

        $transfer->uploadImage($palette, $colors, 4);

        $this->instances = $device->createBuffer(
            self::INSTANCE_SIZE * 16,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT,
            MemoryKind::STREAMING,
        );

        $layout = DescriptorLayout::create($device, [
            DescriptorLayout::binding(
                0,
                VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER,
                VkShaderStageFlagBits::VK_SHADER_STAGE_VERTEX_BIT,
            ),
            DescriptorLayout::binding(
                1,
                VkDescriptorType::VK_DESCRIPTOR_TYPE_SAMPLED_IMAGE,
                VkShaderStageFlagBits::VK_SHADER_STAGE_FRAGMENT_BIT,
            ),
            DescriptorLayout::binding(
                2,
                VkDescriptorType::VK_DESCRIPTOR_TYPE_SAMPLED_IMAGE,
                VkShaderStageFlagBits::VK_SHADER_STAGE_FRAGMENT_BIT,
            ),
        ], 1);

        $this->descriptors = $layout->allocate()
            ->writeBuffer(0, VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER, $this->instances)
            ->writeImage(1, $atlas)
            ->writeImage(2, $palette);

        $this->pipeline = Pipeline::createGraphics($device, new GraphicsPipelineInfo(
            vertexShader: (string) \file_get_contents($shaders . '/quads.vert.spv'),
            fragmentShader: (string) \file_get_contents($shaders . '/quads.frag.spv'),
            colorFormats: [VkFormat::VK_FORMAT_R8G8B8A8_UNORM],
            setLayouts: [$layout->handle],
        ));

        $this->target = $device->createImage(new ImageInfo(
            self::SIZE,
            self::SIZE,
            VkFormat::VK_FORMAT_R8G8B8A8_UNORM,
            VkImageUsageFlagBits::VK_IMAGE_USAGE_COLOR_ATTACHMENT_BIT
                | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_SRC_BIT,
        ));
    }

    /**
     * @param list<array{x: float, y: float, width: float, height: float, tile: int, shift: int}> $quads in NDC
     */
    public function render(array $quads): string
    {
        $data = '';

        foreach ($quads as $quad) {
            $data .= \pack(
                'f4V4',
                $quad['x'],
                $quad['y'],
                $quad['width'],
                $quad['height'],
                $quad['tile'],
                $quad['shift'],
                0,
                0,
            );
        }

        $this->instances->write($data);

        $vk = $this->device->vk;
        $extent = new VkExtent2D(self::SIZE, self::SIZE);

        $this->transfer->submit(function (VkCommandBuffer $commandBuffer) use ($vk, $extent, $quads): void {
            ImageBarrier::transition(
                $vk,
                $commandBuffer,
                $this->target->handle,
                VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED,
                VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL,
            );

            $vk->vkCmdBeginRendering($commandBuffer, new VkRenderingInfo(
                renderArea: new VkRect2D(new VkOffset2D(0, 0), $extent),
                layerCount: 1,
                colorAttachments: [new VkRenderingAttachmentInfo(
                    clearValue: new VkClearValue(color: new VkClearColorValue(float32: [0.0, 1.0, 0.0, 1.0])),
                    imageView: $this->target->view,
                    imageLayout: VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL,
                    loadOp: VkAttachmentLoadOp::VK_ATTACHMENT_LOAD_OP_CLEAR,
                    storeOp: VkAttachmentStoreOp::VK_ATTACHMENT_STORE_OP_STORE,
                )],
            ));

            $vk->vkCmdBindPipeline(
                $commandBuffer,
                VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_GRAPHICS,
                $this->pipeline->handle,
            );
            $vk->vkCmdSetViewport(
                $commandBuffer,
                0,
                [new VkViewport(0.0, 0.0, (float) self::SIZE, (float) self::SIZE, 0.0, 1.0)],
            );
            $vk->vkCmdSetScissor($commandBuffer, 0, [new VkRect2D(new VkOffset2D(0, 0), $extent)]);
            $vk->vkCmdBindDescriptorSets(
                $commandBuffer,
                VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_GRAPHICS,
                $this->pipeline->layout,
                0,
                [$this->descriptors->handle],
                [],
            );
            $vk->vkCmdDraw($commandBuffer, 6, \count($quads), 0, 0);
            $vk->vkCmdEndRendering($commandBuffer);
        });

        $this->target->layout = VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL;

        return $this->transfer->downloadImage($this->target, 4);
    }
}
