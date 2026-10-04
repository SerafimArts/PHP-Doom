<?php

declare(strict_types=1);

namespace Local\GPU\Memory;

use Local\GPU\Exception\DeviceException;
use Serafim\Vulkan\Enum\VkMemoryAllocateFlagBits;
use Serafim\Vulkan\Enum\VkMemoryPropertyFlagBits;
use Serafim\Vulkan\Handle\VkDevice;
use Serafim\Vulkan\Handle\VkDeviceMemory;
use Serafim\Vulkan\Struct\VkMemoryAllocateFlagsInfo;
use Serafim\Vulkan\Struct\VkMemoryAllocateInfo;
use Serafim\Vulkan\Struct\VkMemoryRequirements;
use Serafim\Vulkan\Struct\VkPhysicalDeviceMemoryProperties;
use Serafim\Vulkan\Vulkan;

/**
 * Allocates device memory of the kind that a resource asks for.
 */
final readonly class MemoryAllocator
{
    public function __construct(
        private Vulkan $vk,
        private VkDevice $device,
        private VkPhysicalDeviceMemoryProperties $properties,
    ) {}

    /**
     * @param bool $isAddressable the memory of a buffer that is used by its device address
     *
     * @throws DeviceException in case of no memory type of the kind fits the requirements
     */
    public function allocate(
        VkMemoryRequirements $requirements,
        MemoryKind $kind,
        bool $isAddressable = false,
    ): VkDeviceMemory {
        $chain = [];

        if ($isAddressable) {
            $chain[] = new VkMemoryAllocateFlagsInfo(
                flags: VkMemoryAllocateFlagBits::VK_MEMORY_ALLOCATE_DEVICE_ADDRESS_BIT,
            );
        }

        $local = VkMemoryPropertyFlagBits::VK_MEMORY_PROPERTY_DEVICE_LOCAL_BIT;
        $host = VkMemoryPropertyFlagBits::VK_MEMORY_PROPERTY_HOST_VISIBLE_BIT
            | VkMemoryPropertyFlagBits::VK_MEMORY_PROPERTY_HOST_COHERENT_BIT;

        $candidates = match ($kind) {
            MemoryKind::DEVICE_LOCAL => [$local],
            MemoryKind::HOST_VISIBLE => [$host],
            MemoryKind::STREAMING => [$local | $host, $host],
        };

        foreach ($candidates as $flags) {
            $type = $this->findMemoryType($requirements->memoryTypeBits, $flags);

            if ($type !== null) {
                return $this->vk->vkAllocateMemory($this->device, new VkMemoryAllocateInfo(
                    chain: $chain,
                    allocationSize: $requirements->size,
                    memoryTypeIndex: $type,
                ));
            }
        }

        throw DeviceException::becauseMemoryTypeNotFound($requirements->memoryTypeBits);
    }

    /**
     * @return int<0, 4294967295>|null
     */
    private function findMemoryType(int $typeBits, int $flags): ?int
    {
        for ($i = 0; $i < $this->properties->memoryTypeCount; ++$i) {
            if (($typeBits & (1 << $i)) === 0) {
                continue;
            }

            if (($this->properties->memoryTypes[$i]->propertyFlags & $flags) === $flags) {
                return $i;
            }
        }

        return null;
    }
}
