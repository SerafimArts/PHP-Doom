<?php

declare(strict_types=1);

namespace Local\GPU\Tests;

use Local\GPU\Descriptor\DescriptorLayout;
use Local\GPU\Device;
use Local\GPU\DeviceOptions;
use Local\GPU\Exception\DeviceException;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Pipeline\ComputePipelineInfo;
use Local\GPU\Pipeline\Pipeline;
use Local\GPU\Raytracing\AccelerationStructureBuilder;
use Local\GPU\Resource\Transfer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkDescriptorType;
use Serafim\Vulkan\Enum\VkPipelineBindPoint;
use Serafim\Vulkan\Enum\VkShaderStageFlagBits;
use Serafim\Vulkan\Exception\VulkanException;
use Serafim\Vulkan\Handle\VkCommandBuffer;

#[Group('gpu')]
#[CoversClass(AccelerationStructureBuilder::class)]
final class RayQueryTest extends TestCase
{
    public function testRaysHitTheTriangleOfTheTopLevelStructureAndMissOutsideOfIt(): void
    {
        $shader = \dirname(__DIR__) . '/examples/shaders/ray_query.comp.spv';

        if (!\is_file($shader)) {
            self::markTestSkipped('ray_query.comp.spv is missing, run `php bin/doom build-shaders`');
        }

        $device = $this->createRayQueryDevice();
        $vk = $device->vk;
        $transfer = new Transfer($device);
        $builder = new AccelerationStructureBuilder($device, $transfer);

        $vertices = $device->createBuffer(36, AccelerationStructureBuilder::getInputUsage(), MemoryKind::HOST_VISIBLE);
        $vertices->write(\pack('g9', 0.0, 0.0, 0.0, 2.0, 0.0, 0.0, 0.0, 2.0, 0.0));

        $indices = $device->createBuffer(12, AccelerationStructureBuilder::getInputUsage(), MemoryKind::HOST_VISIBLE);
        $indices->write(\pack('V3', 0, 1, 2));

        $bottom = $builder->buildBottomLevel($vertices, 3, $indices, 1);
        $top = $builder->buildTopLevel([$bottom]);

        $result = $device->createBuffer(
            32,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT,
            MemoryKind::HOST_VISIBLE,
        );
        $result->write(\str_repeat("\0", 32));

        $compute = VkShaderStageFlagBits::VK_SHADER_STAGE_COMPUTE_BIT;
        $layout = DescriptorLayout::create($device, [
            DescriptorLayout::binding(0, VkDescriptorType::VK_DESCRIPTOR_TYPE_ACCELERATION_STRUCTURE_KHR, $compute),
            DescriptorLayout::binding(1, VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER, $compute),
        ], 1);
        $set = $layout->allocate()
            ->writeAccelerationStructure(0, $top)
            ->writeBuffer(1, VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER, $result);

        $code = \file_get_contents($shader);
        \assert(\is_string($code) && $code !== '');

        $pipeline = Pipeline::createCompute($device, new ComputePipelineInfo($code, [$layout->handle]));

        $transfer->submit(static function (VkCommandBuffer $commandBuffer) use ($vk, $pipeline, $set): void {
            $vk->vkCmdBindPipeline(
                $commandBuffer,
                VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_COMPUTE,
                $pipeline->handle,
            );
            // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
            $vk->vkCmdBindDescriptorSets(
                $commandBuffer,
                VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_COMPUTE,
                $pipeline->layout,
                0,
                [$set->handle],
                [],
            );
            $vk->vkCmdDispatch($commandBuffer, 1, 1, 1);
        });

        $distances = \array_values(\unpack('g8', $result->read(32)) ?: []);

        self::assertSame([4.0, 4.0, -1.0, -1.0, -1.0, -1.0, -1.0, -1.0], $distances);
        $this->assertNoValidationMessages();
    }

    private function createRayQueryDevice(): Device
    {
        try {
            return Device::create(null, new DeviceOptions(
                applicationName: 'tests',
                isValidationEnabled: true,
                isRayQueryEnabled: true,
                onValidationMessage: function (string $message): void {
                    $this->validationMessages[] = $message;
                },
            ));
        } catch (DeviceException|VulkanException $e) {
            self::markTestSkipped('Ray queries are not available: ' . $e->getMessage());
        }
    }
}
