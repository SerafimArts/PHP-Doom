<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Gpu;

use Local\GPU\Descriptor\DescriptorLayout;
use Local\GPU\Device;
use Local\GPU\Frame\FrameLoop;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Resource\Buffer;
use Serafim\Doom\Render\Layout\DrawCommand;
use Serafim\Doom\Render\Layout\FrameData;
use Serafim\Doom\Render\Layout\QuadInstance;
use Serafim\Doom\Render\Layout\SpriteInstance;
use Serafim\Doom\Render\Raytracing\ShadowScene;
use Serafim\Doom\Render\Scene;
use Serafim\Doom\Render\SectorTable;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkDescriptorType;
use Serafim\Vulkan\Enum\VkShaderStageFlagBits;
use Serafim\Vulkan\Struct\VkDescriptorSetLayoutBinding;

/**
 * The descriptor set of the level: the bindings that `level.glsl` and the shaders declare, and the sets of the
 * frames in flight.
 */
final readonly class LevelDescriptors
{
    public DescriptorLayout $layout;

    public function __construct(
        private Device $device,
        private bool $hasRayQueries,
    ) {
        $this->layout = DescriptorLayout::create($device, $this->createBindings(), FrameLoop::FRAMES_IN_FLIGHT);
    }

    /**
     * Makes the buffers and the descriptor set of a frame in flight.
     *
     * @param int<0, max> $frameIndex
     * @param int<0, max> $sectorCount
     * @param int<0, max> $wallCount
     */
    public function createFrame(
        int $frameIndex,
        StaticResources $resources,
        int $sectorCount,
        int $wallCount,
        ?ShadowScene $shadows,
    ): FrameResources {
        $uniform = VkBufferUsageFlagBits::VK_BUFFER_USAGE_UNIFORM_BUFFER_BIT;
        $storage = VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT;

        $frame = $this->createStreaming(FrameData::SIZE, $uniform);
        $sectors = $this->createStreaming(\max(1, $sectorCount) * SectorTable::SECTOR_SIZE, $storage);
        $pictures = $this->createStreaming(\max(4, $wallCount * 4), $storage);
        $sprites = $this->createStreaming(Scene::MAX_SPRITES * SpriteInstance::SIZE, $storage);
        $quads = $this->createStreaming(Scene::MAX_QUADS * QuadInstance::SIZE, $storage);
        $draw = $this->createStreaming(
            DrawCommand::BUFFER_SIZE,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_INDIRECT_BUFFER_BIT,
        );

        $buffer = VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER;
        $set = $this->layout->allocate()
            ->writeBuffer(0, VkDescriptorType::VK_DESCRIPTOR_TYPE_UNIFORM_BUFFER, $frame)
            ->writeBuffer(1, $buffer, $sectors)
            ->writeBuffer(2, $buffer, $resources->rects)
            ->writeBuffer(3, $buffer, $resources->walls)
            ->writeImage(4, $resources->atlas)
            ->writeImage(5, $resources->colormap)
            ->writeImage(6, $resources->palettes)
            ->writeBuffer(7, $buffer, $pictures)
            ->writeBuffer(8, $buffer, $resources->spriteViews)
            ->writeBuffer(9, $buffer, $sprites)
            ->writeBuffer(10, $buffer, $quads);

        if ($shadows !== null) {
            $set->writeAccelerationStructure(11, $shadows->getStructure($frameIndex))
                ->writeBuffer(12, $buffer, $shadows->getLights($frameIndex));
        }

        return new FrameResources($frame, $sectors, $pictures, $sprites, $quads, $draw, $set);
    }

    /**
     * @return non-empty-list<VkDescriptorSetLayoutBinding>
     */
    private function createBindings(): array
    {
        $vertex = VkShaderStageFlagBits::VK_SHADER_STAGE_VERTEX_BIT;
        $fragment = VkShaderStageFlagBits::VK_SHADER_STAGE_FRAGMENT_BIT;
        $uniform = VkDescriptorType::VK_DESCRIPTOR_TYPE_UNIFORM_BUFFER;
        $buffer = VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER;
        $image = VkDescriptorType::VK_DESCRIPTOR_TYPE_SAMPLED_IMAGE;

        $bindings = [
            DescriptorLayout::binding(0, $uniform, $vertex | $fragment),
            DescriptorLayout::binding(1, $buffer, $vertex | $fragment),
            DescriptorLayout::binding(2, $buffer, $vertex | $fragment),
            DescriptorLayout::binding(3, $buffer, $vertex),
            DescriptorLayout::binding(4, $image, $fragment),
            DescriptorLayout::binding(5, $image, $fragment),
            DescriptorLayout::binding(6, $image, $fragment),
            DescriptorLayout::binding(7, $buffer, $vertex),
            DescriptorLayout::binding(8, $buffer, $vertex),
            DescriptorLayout::binding(9, $buffer, $vertex),
            DescriptorLayout::binding(10, $buffer, $vertex),
        ];

        if ($this->hasRayQueries) {
            $bindings[] = DescriptorLayout::binding(
                11,
                VkDescriptorType::VK_DESCRIPTOR_TYPE_ACCELERATION_STRUCTURE_KHR,
                $fragment,
            );
            $bindings[] = DescriptorLayout::binding(12, $buffer, $fragment);
        }

        return $bindings;
    }

    /**
     * @param int<1, max> $size
     * @param int<0, 4294967295> $usage
     */
    private function createStreaming(int $size, int $usage): Buffer
    {
        return $this->device->createBuffer($size, $usage, MemoryKind::STREAMING);
    }
}
