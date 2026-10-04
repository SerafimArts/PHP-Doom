<?php

declare(strict_types=1);

namespace Local\GPU\Descriptor;

use Local\GPU\Device;
use Local\GPU\Raytracing\AccelerationStructure;
use Local\GPU\Resource\Buffer;
use Local\GPU\Resource\Image;
use Serafim\Vulkan\Enum\VkDescriptorType;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Handle\VkDescriptorSet;
use Serafim\Vulkan\Handle\VkSampler;
use Serafim\Vulkan\Struct\VkDescriptorBufferInfo;
use Serafim\Vulkan\Struct\VkDescriptorImageInfo;
use Serafim\Vulkan\Struct\VkWriteDescriptorSet;
use Serafim\Vulkan\Struct\VkWriteDescriptorSetAccelerationStructureKHR;

use const Serafim\Vulkan\VK_WHOLE_SIZE;

/**
 * A descriptor set. The resources written into it stay alive as long as the set does.
 */
final readonly class DescriptorSet
{
    public function __construct(
        private Device $device,
        public VkDescriptorSet $handle,
    ) {}

    /**
     * Binds a whole buffer, as a uniform or a storage buffer.
     *
     * @param int<0, 4294967295> $binding
     */
    public function writeBuffer(int $binding, VkDescriptorType $type, Buffer $buffer): self
    {
        // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
        $this->device->vk->vkUpdateDescriptorSets($this->device->device, [new VkWriteDescriptorSet(
            dstSet: $this->handle,
            dstBinding: $binding,
            descriptorType: $type,
            bufferInfo: [new VkDescriptorBufferInfo($buffer->handle, 0, VK_WHOLE_SIZE)],
        )], []);

        $this->handle->retain($buffer);

        return $this;
    }

    /**
     * Binds an image as a sampled image (without a sampler) or as a combined image sampler.
     *
     * @param int<0, 4294967295> $binding
     */
    public function writeImage(int $binding, Image $image, ?VkSampler $sampler = null): self
    {
        $type = VkDescriptorType::VK_DESCRIPTOR_TYPE_SAMPLED_IMAGE;

        if ($sampler !== null) {
            $type = VkDescriptorType::VK_DESCRIPTOR_TYPE_COMBINED_IMAGE_SAMPLER;
        }

        // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
        $this->device->vk->vkUpdateDescriptorSets($this->device->device, [new VkWriteDescriptorSet(
            dstSet: $this->handle,
            dstBinding: $binding,
            descriptorType: $type,
            imageInfo: [new VkDescriptorImageInfo(
                $sampler ?? $this->device->createNearestSampler(),
                $image->view,
                VkImageLayout::VK_IMAGE_LAYOUT_SHADER_READ_ONLY_OPTIMAL,
            )],
        )], []);

        $this->handle->retain($image);

        return $this;
    }

    /**
     * Binds an acceleration structure for ray queries.
     *
     * @param int<0, 4294967295> $binding
     */
    public function writeAccelerationStructure(int $binding, AccelerationStructure $structure): self
    {
        // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
        $this->device->vk->vkUpdateDescriptorSets($this->device->device, [new VkWriteDescriptorSet(
            dstSet: $this->handle,
            chain: [new VkWriteDescriptorSetAccelerationStructureKHR([$structure->handle])],
            dstBinding: $binding,
            descriptorType: VkDescriptorType::VK_DESCRIPTOR_TYPE_ACCELERATION_STRUCTURE_KHR,
            bufferInfo: [new VkDescriptorBufferInfo($structure->buffer->handle, 0, VK_WHOLE_SIZE)],
        )], []);

        $this->handle->retain($structure);

        return $this;
    }
}
