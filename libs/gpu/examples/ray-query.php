<?php

declare(strict_types=1);

// Ray query check of local/gpu: one triangle goes into a bottom level and a top level acceleration structure, a compute
// shader shoots eight rays at it and the distances come back to the host.
// Run: php libs/gpu/examples/ray-query.php [validate]   (shaders: php bin/doom build-shaders)

use Local\GPU\Descriptor\DescriptorLayout;
use Local\GPU\Device;
use Local\GPU\DeviceOptions;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Pipeline\ComputePipelineInfo;
use Local\GPU\Pipeline\Pipeline;
use Local\GPU\Raytracing\AccelerationStructureBuilder;
use Local\GPU\Resource\Transfer;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkDescriptorType;
use Serafim\Vulkan\Enum\VkPipelineBindPoint;
use Serafim\Vulkan\Enum\VkShaderStageFlagBits;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

$messages = [];

$device = Device::create(null, new DeviceOptions(
    applicationName: 'ray-query',
    isValidationEnabled: ($argv[1] ?? '') === 'validate',
    isRayQueryEnabled: true,
    onValidationMessage: static function (string $message) use (&$messages): void {
        $messages[] = $message;
    },
));
$vk = $device->vk;
$transfer = new Transfer($device);
$builder = new AccelerationStructureBuilder($device, $transfer);

$vertices = $device->createBuffer(36, AccelerationStructureBuilder::getInputUsage(), MemoryKind::HOST_VISIBLE);
$vertices->write(\pack('g9', 0.0, 0.0, 0.0, 2.0, 0.0, 0.0, 0.0, 2.0, 0.0));

$indices = $device->createBuffer(12, AccelerationStructureBuilder::getInputUsage(), MemoryKind::HOST_VISIBLE);
$indices->write(\pack('V3', 0, 1, 2));

$bottom = $builder->buildBottomLevel($vertices, 3, $indices, 1);
$top = $builder->buildTopLevel([$bottom]);

$result = $device->createBuffer(32, VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT, MemoryKind::HOST_VISIBLE);
$result->write(\str_repeat("\0", 32));

$layout = DescriptorLayout::create($device, [
    DescriptorLayout::binding(0, VkDescriptorType::VK_DESCRIPTOR_TYPE_ACCELERATION_STRUCTURE_KHR, VkShaderStageFlagBits::VK_SHADER_STAGE_COMPUTE_BIT),
    DescriptorLayout::binding(1, VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER, VkShaderStageFlagBits::VK_SHADER_STAGE_COMPUTE_BIT),
], 1);
$set = $layout->allocate()
    ->writeAccelerationStructure(0, $top)
    ->writeBuffer(1, VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER, $result);

$code = \file_get_contents(__DIR__ . '/shaders/ray_query.comp.spv');
\assert(\is_string($code) && $code !== '');

$pipeline = Pipeline::createCompute($device, new ComputePipelineInfo($code, [$layout->handle]));

$transfer->submit(static function ($commandBuffer) use ($vk, $pipeline, $set): void {
    $vk->vkCmdBindPipeline($commandBuffer, VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_COMPUTE, $pipeline->handle);
    // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
    $vk->vkCmdBindDescriptorSets($commandBuffer, VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_COMPUTE, $pipeline->layout, 0, [$set->handle], []);
    $vk->vkCmdDispatch($commandBuffer, 1, 1, 1);
});

/** @var list<float> $distances */
$distances = \array_values(\unpack('g8', $result->read(32)) ?: []);

echo 'device: ', $device->getDeviceName(), "\n";
echo 'distances: ', \implode(' ', $distances), "\n";

$expected = [4.0, 4.0, -1.0, -1.0, -1.0, -1.0, -1.0, -1.0];
$isCorrect = $distances === $expected;

echo $isCorrect ? "OK\n" : "WRONG, expected " . \implode(' ', $expected) . "\n";

foreach ($messages as $message) {
    echo "validation: $message\n";
}

exit($isCorrect && $messages === [] ? 0 : 1);
