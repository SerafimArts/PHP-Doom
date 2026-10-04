<?php

declare(strict_types=1);

namespace Local\GPU\Raytracing;

use FFI\CData;
use Local\GPU\Device;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Resource\Buffer;
use Local\GPU\Resource\Transfer;
use Serafim\Vulkan\Enum\VkAccelerationStructureBuildTypeKHR;
use Serafim\Vulkan\Enum\VkAccelerationStructureTypeKHR;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkBuildAccelerationStructureFlagBitsKHR;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkGeometryFlagBitsKHR;
use Serafim\Vulkan\Enum\VkGeometryTypeKHR;
use Serafim\Vulkan\Enum\VkIndexType;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Struct\VkAccelerationStructureBuildGeometryInfoKHR;
use Serafim\Vulkan\Struct\VkAccelerationStructureBuildSizesInfoKHR;
use Serafim\Vulkan\Struct\VkAccelerationStructureCreateInfoKHR;
use Serafim\Vulkan\Struct\VkAccelerationStructureDeviceAddressInfoKHR;
use Serafim\Vulkan\Struct\VkAccelerationStructureGeometryDataKHR;
use Serafim\Vulkan\Struct\VkAccelerationStructureGeometryInstancesDataKHR;
use Serafim\Vulkan\Struct\VkAccelerationStructureGeometryKHR;
use Serafim\Vulkan\Struct\VkAccelerationStructureGeometryTrianglesDataKHR;
use Serafim\Vulkan\Struct\VkDeviceOrHostAddressConstKHR;
use Serafim\Vulkan\Struct\VkDeviceOrHostAddressKHR;

/**
 * Builds bottom level structures over triangle meshes and a top level structure over their instances.
 */
final readonly class AccelerationStructureBuilder
{
    /**
     * Bytes of one `VkAccelerationStructureInstanceKHR`
     */
    private const int INSTANCE_SIZE = 64;

    public function __construct(
        private Device $device,
        private Transfer $transfer,
    ) {}

    /**
     * Usage flags of a buffer that holds vertices, indices or instances read by a build.
     *
     * @return int<0, 4294967295>
     */
    public static function getInputUsage(): int
    {
        return VkBufferUsageFlagBits::VK_BUFFER_USAGE_ACCELERATION_STRUCTURE_BUILD_INPUT_READ_ONLY_BIT_KHR
            | VkBufferUsageFlagBits::VK_BUFFER_USAGE_SHADER_DEVICE_ADDRESS_BIT;
    }

    /**
     * @param Buffer $vertices tightly packed `float` triples
     * @param Buffer $indices `uint32_t` triangle indices
     * @param int<1, 4294967296> $vertexCount
     * @param int<1, max> $triangleCount
     */
    public function buildBottomLevel(
        Buffer $vertices,
        int $vertexCount,
        Buffer $indices,
        int $triangleCount,
    ): AccelerationStructure {
        return $this->build(
            VkAccelerationStructureTypeKHR::VK_ACCELERATION_STRUCTURE_TYPE_BOTTOM_LEVEL_KHR,
            $this->createTriangles($vertices, $vertexCount, $indices),
            $triangleCount,
        );
    }

    /**
     * A bottom level structure over triangles that the host rewrites between frames: the command that rebuilds it is
     * recorded by the caller.
     *
     * @param int<1, 4294967296> $vertexCount
     * @param int<1, max> $triangleCount
     */
    public function createRebuiltBottomLevel(
        Buffer $vertices,
        int $vertexCount,
        Buffer $indices,
        int $triangleCount,
    ): BuildableStructure {
        return $this->prepare(
            VkAccelerationStructureTypeKHR::VK_ACCELERATION_STRUCTURE_TYPE_BOTTOM_LEVEL_KHR,
            $this->createTriangles($vertices, $vertexCount, $indices),
            $triangleCount,
            true,
        );
    }

    /**
     * A top level structure over the instances that the host writes into the buffer before every frame, as many as the
     * buffer holds (64 bytes each, an instance that refers to no structure is skipped).
     *
     * @param int<1, max> $instanceCount
     */
    public function createRebuiltTopLevel(Buffer $instances, int $instanceCount): BuildableStructure
    {
        return $this->prepare(
            VkAccelerationStructureTypeKHR::VK_ACCELERATION_STRUCTURE_TYPE_TOP_LEVEL_KHR,
            $this->createInstances($instances),
            $instanceCount,
            true,
        );
    }

    /**
     * Places every structure once with the identity transform.
     *
     * @param non-empty-list<AccelerationStructure> $instances
     */
    public function buildTopLevel(array $instances): AccelerationStructure
    {
        $data = '';

        foreach ($instances as $index => $instance) {
            $data .= \pack('g12', 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0);
            $data .= \pack('V', ($index & 0xFF_FFFF) | (0xFF << 24));
            $data .= \pack('V', 0);
            $data .= \pack('P', $instance->address);
        }

        $buffer = $this->device->createBuffer(
            \max(1, \count($instances) * self::INSTANCE_SIZE),
            self::getInputUsage(),
            MemoryKind::HOST_VISIBLE,
        );
        $buffer->write($data);

        return $this->build(
            VkAccelerationStructureTypeKHR::VK_ACCELERATION_STRUCTURE_TYPE_TOP_LEVEL_KHR,
            $this->createInstances($buffer),
            \count($instances),
        );
    }

    /**
     * @param int<1, max> $primitiveCount
     */
    private function build(
        VkAccelerationStructureTypeKHR $type,
        VkAccelerationStructureGeometryKHR $geometry,
        int $primitiveCount,
    ): AccelerationStructure {
        $buildable = $this->prepare($type, $geometry, $primitiveCount, false);

        $this->transfer->submit(static function (VkCommandBuffer $commandBuffer) use ($buildable): void {
            $buildable->record($commandBuffer);
        });

        return $buildable->structure;
    }

    /**
     * @param int<1, max> $primitiveCount
     */
    private function prepare(
        VkAccelerationStructureTypeKHR $type,
        VkAccelerationStructureGeometryKHR $geometry,
        int $primitiveCount,
        bool $isRebuilt,
    ): BuildableStructure {
        $vk = $this->device->vk;
        $flags = VkBuildAccelerationStructureFlagBitsKHR::VK_BUILD_ACCELERATION_STRUCTURE_PREFER_FAST_TRACE_BIT_KHR;

        if ($isRebuilt) {
            $flags = VkBuildAccelerationStructureFlagBitsKHR::VK_BUILD_ACCELERATION_STRUCTURE_PREFER_FAST_BUILD_BIT_KHR;
        }

        $sizes = $this->getSizes(
            new VkAccelerationStructureBuildGeometryInfoKHR(
                scratchData: new VkDeviceOrHostAddressKHR(deviceAddress: 0),
                type: $type,
                flags: $flags,
                geometryCount: 1,
                geometries: [$geometry],
            ),
            $primitiveCount,
        );

        $storage = $this->device->createBuffer(
            \max(1, $sizes->accelerationStructureSize),
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_ACCELERATION_STRUCTURE_STORAGE_BIT_KHR
                | VkBufferUsageFlagBits::VK_BUFFER_USAGE_SHADER_DEVICE_ADDRESS_BIT,
            MemoryKind::DEVICE_LOCAL,
        );

        $scratch = $this->device->createBuffer(
            \max(1, $sizes->buildScratchSize),
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT
                | VkBufferUsageFlagBits::VK_BUFFER_USAGE_SHADER_DEVICE_ADDRESS_BIT,
            MemoryKind::DEVICE_LOCAL,
        );

        // @phpstan-ignore method.deprecated (the replacement needs VK_KHR_device_address_commands that is not enabled)
        $handle = $vk->vkCreateAccelerationStructureKHR($this->device->device, new VkAccelerationStructureCreateInfoKHR(
            buffer: $storage->handle,
            size: $sizes->accelerationStructureSize,
            type: $type,
        ));

        $info = new VkAccelerationStructureBuildGeometryInfoKHR(
            scratchData: new VkDeviceOrHostAddressKHR(deviceAddress: $this->device->getBufferAddress($scratch)),
            type: $type,
            flags: $flags,
            dstAccelerationStructure: $handle,
            geometryCount: 1,
            geometries: [$geometry],
        );

        $native = $this->createNative($info);

        $range = $vk->ffi->new('VkAccelerationStructureBuildRangeInfoKHR');
        $range->primitiveCount = $primitiveCount;

        $ranges = $vk->ffi->new('VkAccelerationStructureBuildRangeInfoKHR*[1]');
        $ranges[0] = \FFI::addr($range);
        \assert($ranges instanceof CData);

        $address = $vk->vkGetAccelerationStructureDeviceAddressKHR(
            $this->device->device,
            new VkAccelerationStructureDeviceAddressInfoKHR(accelerationStructure: $handle),
        );

        return new BuildableStructure(
            new AccelerationStructure($handle, $storage, $address),
            $vk,
            $scratch,
            $native,
            $range,
            $ranges,
        );
    }

    /**
     * @param int<1, 4294967296> $vertexCount
     */
    private function createTriangles(
        Buffer $vertices,
        int $vertexCount,
        Buffer $indices,
    ): VkAccelerationStructureGeometryKHR {
        return new VkAccelerationStructureGeometryKHR(
            geometry: new VkAccelerationStructureGeometryDataKHR(
                triangles: new VkAccelerationStructureGeometryTrianglesDataKHR(
                    vertexData: new VkDeviceOrHostAddressConstKHR(
                        deviceAddress: $this->device->getBufferAddress($vertices),
                    ),
                    indexData: new VkDeviceOrHostAddressConstKHR(
                        deviceAddress: $this->device->getBufferAddress($indices),
                    ),
                    transformData: new VkDeviceOrHostAddressConstKHR(deviceAddress: 0),
                    vertexFormat: VkFormat::VK_FORMAT_R32G32B32_SFLOAT,
                    vertexStride: 12,
                    maxVertex: $vertexCount - 1,
                    indexType: VkIndexType::VK_INDEX_TYPE_UINT32,
                ),
            ),
            geometryType: VkGeometryTypeKHR::VK_GEOMETRY_TYPE_TRIANGLES_KHR,
            flags: VkGeometryFlagBitsKHR::VK_GEOMETRY_OPAQUE_BIT_KHR,
        );
    }

    private function createInstances(Buffer $instances): VkAccelerationStructureGeometryKHR
    {
        return new VkAccelerationStructureGeometryKHR(
            geometry: new VkAccelerationStructureGeometryDataKHR(
                instances: new VkAccelerationStructureGeometryInstancesDataKHR(
                    data: new VkDeviceOrHostAddressConstKHR(deviceAddress: $this->device->getBufferAddress($instances)),
                ),
            ),
            geometryType: VkGeometryTypeKHR::VK_GEOMETRY_TYPE_INSTANCES_KHR,
        );
    }

    /**
     * The geometries of a build are passed as an array and never as an array of pointers.
     */
    private function createNative(VkAccelerationStructureBuildGeometryInfoKHR $info): CData
    {
        // @phpstan-ignore property.internal (the structure writes itself only into the allocator of the library)
        $native = $info->toCData($this->device->vk->memory);
        $native->ppGeometries = null;

        return $native;
    }

    /**
     * @param int<1, max> $primitiveCount
     */
    private function getSizes(
        VkAccelerationStructureBuildGeometryInfoKHR $info,
        int $primitiveCount,
    ): VkAccelerationStructureBuildSizesInfoKHR {
        $vk = $this->device->vk;

        $native = $this->createNative($info);

        $counts = $vk->ffi->new('uint32_t[1]');
        $counts[0] = $primitiveCount;

        $sizes = $vk->ffi->new('VkAccelerationStructureBuildSizesInfoKHR');
        $sizes->sType = VkAccelerationStructureBuildSizesInfoKHR::STRUCTURE_TYPE;

        $query = $vk->vkGetInstanceProc('vkGetAccelerationStructureBuildSizesKHR', $this->device->device);
        $query(
            $this->device->device->pointer,
            VkAccelerationStructureBuildTypeKHR::VK_ACCELERATION_STRUCTURE_BUILD_TYPE_DEVICE_KHR->value,
            \FFI::addr($native),
            $counts,
            \FFI::addr($sizes),
        );

        $accelerationStructureSize = $sizes->accelerationStructureSize;
        $buildScratchSize = $sizes->buildScratchSize;
        \assert(\is_int($accelerationStructureSize) && \is_int($buildScratchSize));

        return new VkAccelerationStructureBuildSizesInfoKHR($accelerationStructureSize, 0, $buildScratchSize);
    }
}
