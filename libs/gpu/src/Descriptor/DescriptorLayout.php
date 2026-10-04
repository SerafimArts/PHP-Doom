<?php

declare(strict_types=1);

namespace Local\GPU\Descriptor;

use Local\GPU\Device;
use Serafim\Vulkan\Enum\VkDescriptorType;
use Serafim\Vulkan\Handle\VkDescriptorPool;
use Serafim\Vulkan\Handle\VkDescriptorSetLayout;
use Serafim\Vulkan\Struct\VkDescriptorPoolCreateInfo;
use Serafim\Vulkan\Struct\VkDescriptorPoolSize;
use Serafim\Vulkan\Struct\VkDescriptorSetAllocateInfo;
use Serafim\Vulkan\Struct\VkDescriptorSetLayoutBinding;
use Serafim\Vulkan\Struct\VkDescriptorSetLayoutCreateInfo;

/**
 * A descriptor set layout with the pool its sets come from.
 */
final readonly class DescriptorLayout
{
    private function __construct(
        private Device $device,
        public VkDescriptorSetLayout $handle,
        private VkDescriptorPool $pool,
    ) {}

    /**
     * @param non-empty-list<VkDescriptorSetLayoutBinding> $bindings
     * @param int<1, 4294967295> $maxSets the number of sets that can be allocated
     */
    public static function create(Device $device, array $bindings, int $maxSets = 4): self
    {
        $vk = $device->vk;

        $sizes = [];

        foreach ($bindings as $binding) {
            $sizes[$binding->descriptorType->value] = new VkDescriptorPoolSize(
                $binding->descriptorType,
                \min(
                    4_294_967_295,
                    ($sizes[$binding->descriptorType->value]->descriptorCount ?? 0)
                        + $binding->descriptorCount * $maxSets,
                ),
            );
        }

        return new self(
            $device,
            // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
            $vk->vkCreateDescriptorSetLayout($device->device, new VkDescriptorSetLayoutCreateInfo(bindings: $bindings)),
            // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
            $vk->vkCreateDescriptorPool($device->device, new VkDescriptorPoolCreateInfo(
                maxSets: $maxSets,
                poolSizes: \array_values($sizes),
            )),
        );
    }

    public function allocate(): DescriptorSet
    {
        // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
        [$set] = $this->device->vk->vkAllocateDescriptorSets(
            $this->device->device,
            new VkDescriptorSetAllocateInfo($this->pool, setLayouts: [$this->handle]),
        );

        return new DescriptorSet($this->device, $set);
    }

    /**
     * @param int<0, 4294967295> $binding
     * @param int<0, 4294967295> $stages stage flags of `VkShaderStageFlagBits`
     */
    public static function binding(int $binding, VkDescriptorType $type, int $stages): VkDescriptorSetLayoutBinding
    {
        return new VkDescriptorSetLayoutBinding($binding, $type, 1, $stages);
    }
}
