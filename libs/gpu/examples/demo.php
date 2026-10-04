<?php

declare(strict_types=1);

// Demo of local/gpu: a window, a frame loop with pre-recorded command buffers, instanced quads from a storage buffer,
// indexed color through an atlas and a palette, an indirect draw, and a nearest-neighbour upscale of a 640x400 image.
// The host writes two small buffers per frame and nothing else.
// Run: php libs/gpu/examples/demo.php [validate] [seconds]   (ESC closes the window, shaders: php bin/doom build-shaders)

use Local\GPU\Descriptor\DescriptorLayout;
use Local\GPU\Device;
use Local\GPU\DeviceOptions;
use Local\GPU\Frame\FrameLoop;
use Local\GPU\Frame\FrameTarget;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Pipeline\GraphicsPipelineInfo;
use Local\GPU\Pipeline\Pipeline;
use Local\GPU\Resource\ImageInfo;
use Local\GPU\Resource\Transfer;
use Local\GPU\Sync\ImageBarrier;
use Local\GLFW\Constants\Key;
use Local\GPU\Window\Window;
use Local\GPU\Window\WindowOptions;
use Serafim\Vulkan\Enum\VkAttachmentLoadOp;
use Serafim\Vulkan\Enum\VkAttachmentStoreOp;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkDescriptorType;
use Serafim\Vulkan\Enum\VkFilter;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageAspectFlagBits;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Enum\VkImageUsageFlagBits;
use Serafim\Vulkan\Enum\VkPipelineBindPoint;
use Serafim\Vulkan\Enum\VkShaderStageFlagBits;
use Serafim\Vulkan\Struct\VkClearColorValue;
use Serafim\Vulkan\Struct\VkClearValue;
use Serafim\Vulkan\Struct\VkExtent2D;
use Serafim\Vulkan\Struct\VkImageBlit;
use Serafim\Vulkan\Struct\VkImageSubresourceLayers;
use Serafim\Vulkan\Struct\VkImageSubresourceRange;
use Serafim\Vulkan\Struct\VkOffset2D;
use Serafim\Vulkan\Struct\VkOffset3D;
use Serafim\Vulkan\Struct\VkRect2D;
use Serafim\Vulkan\Struct\VkRenderingAttachmentInfo;
use Serafim\Vulkan\Struct\VkRenderingInfo;
use Serafim\Vulkan\Struct\VkViewport;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

const RENDER_WIDTH = 640;
const RENDER_HEIGHT = 400;
const QUADS = 16;

$window = new Window(new WindowOptions(title: 'local/gpu demo', width: 1024, height: 768));
$device = Device::create($window, new DeviceOptions(
    applicationName: 'demo',
    isValidationEnabled: ($argv[1] ?? '') === 'validate',
));
$vk = $device->vk;
$transfer = new Transfer($device);

echo 'GPU: ', $device->getDeviceName(), "\n";

// Indexed-color atlas (4x4 tiles of 16x16) and a palette that is a color ramp.
$atlas = $device->createImage(new ImageInfo(
    64,
    64,
    VkFormat::VK_FORMAT_R8_UNORM,
    VkImageUsageFlagBits::VK_IMAGE_USAGE_SAMPLED_BIT | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_DST_BIT,
));
$pixels = '';

for ($y = 0; $y < 64; ++$y) {
    for ($x = 0; $x < 64; ++$x) {
        $tile = \intdiv($y, 16) * 4 + \intdiv($x, 16);
        $checker = (($x ^ $y) & 4) !== 0 ? 0 : 8;
        $pixels .= \chr(($tile * 16 + $checker) & 255);
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
    $colors .= \chr((int) (127 + 127 * \sin($i / 40))) . \chr((int) (127 + 127 * \sin($i / 25 + 2))) . \chr((int) (127 + 127 * \sin($i / 17 + 4))) . "\xFF";
}

$transfer->uploadImage($palette, $colors, 4);

// Data of the frames: one set of buffers and one descriptor set for every frame in flight.
$layout = DescriptorLayout::create($device, [
    DescriptorLayout::binding(0, VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER, VkShaderStageFlagBits::VK_SHADER_STAGE_VERTEX_BIT),
    DescriptorLayout::binding(1, VkDescriptorType::VK_DESCRIPTOR_TYPE_SAMPLED_IMAGE, VkShaderStageFlagBits::VK_SHADER_STAGE_FRAGMENT_BIT),
    DescriptorLayout::binding(2, VkDescriptorType::VK_DESCRIPTOR_TYPE_SAMPLED_IMAGE, VkShaderStageFlagBits::VK_SHADER_STAGE_FRAGMENT_BIT),
], FrameLoop::FRAMES_IN_FLIGHT);

$instances = [];
$indirect = [];
$descriptors = [];

for ($i = 0; $i < FrameLoop::FRAMES_IN_FLIGHT; ++$i) {
    $instances[$i] = $device->createBuffer(32 * QUADS, VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT, MemoryKind::STREAMING);
    $indirect[$i] = $device->createBuffer(16, VkBufferUsageFlagBits::VK_BUFFER_USAGE_INDIRECT_BUFFER_BIT, MemoryKind::STREAMING);
    $descriptors[$i] = $layout->allocate()
        ->writeBuffer(0, VkDescriptorType::VK_DESCRIPTOR_TYPE_STORAGE_BUFFER, $instances[$i])
        ->writeImage(1, $atlas)
        ->writeImage(2, $palette);
}

$pipeline = Pipeline::createGraphics($device, new GraphicsPipelineInfo(
    vertexShader: (string) \file_get_contents(__DIR__ . '/shaders/quads.vert.spv'),
    fragmentShader: (string) \file_get_contents(__DIR__ . '/shaders/quads.frag.spv'),
    colorFormats: [VkFormat::VK_FORMAT_R8G8B8A8_UNORM],
    setLayouts: [$layout->handle],
));

$canvas = $device->createImage(new ImageInfo(
    RENDER_WIDTH,
    RENDER_HEIGHT,
    VkFormat::VK_FORMAT_R8G8B8A8_UNORM,
    VkImageUsageFlagBits::VK_IMAGE_USAGE_COLOR_ATTACHMENT_BIT | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_SRC_BIT,
));

// The commands never change between the frames, they are recorded again only when the swapchain is recreated.
$loop = new FrameLoop($device, static function (FrameTarget $target) use ($vk, $canvas, $pipeline, $descriptors, $indirect): void {
    $cmd = $target->commandBuffer;
    $canvasArea = new VkRect2D(new VkOffset2D(0, 0), new VkExtent2D(RENDER_WIDTH, RENDER_HEIGHT));

    ImageBarrier::transition($vk, $cmd, $canvas->handle, VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED, VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL);
    $vk->vkCmdBeginRendering($cmd, new VkRenderingInfo(
        renderArea: $canvasArea,
        layerCount: 1,
        colorAttachments: [new VkRenderingAttachmentInfo(
            clearValue: new VkClearValue(color: new VkClearColorValue(float32: [0.02, 0.02, 0.05, 1.0])),
            imageView: $canvas->view,
            imageLayout: VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL,
            loadOp: VkAttachmentLoadOp::VK_ATTACHMENT_LOAD_OP_CLEAR,
            storeOp: VkAttachmentStoreOp::VK_ATTACHMENT_STORE_OP_STORE,
        )],
    ));
    $vk->vkCmdBindPipeline($cmd, VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_GRAPHICS, $pipeline->handle);
    $vk->vkCmdSetViewport($cmd, 0, [new VkViewport(0.0, 0.0, (float) RENDER_WIDTH, (float) RENDER_HEIGHT, 0.0, 1.0)]);
    $vk->vkCmdSetScissor($cmd, 0, [$canvasArea]);
    $vk->vkCmdBindDescriptorSets($cmd, VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_GRAPHICS, $pipeline->layout, 0, [$descriptors[$target->frameIndex]->handle], []);
    $vk->vkCmdDrawIndirect($cmd, $indirect[$target->frameIndex]->handle, 0, 1, 16);
    $vk->vkCmdEndRendering($cmd);
    ImageBarrier::transition($vk, $cmd, $canvas->handle, VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL, VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_SRC_OPTIMAL);

    // Letterbox: the canvas is shown as 4:3 (the pixels of Doom are taller than wide), black bars around it.
    $width = $target->extent->width;
    $height = $target->extent->height;
    $shownWidth = \min($width, (int) ($height * 4 / 3));
    $shownHeight = \min($height, (int) ($width * 3 / 4));
    $left = \intdiv($width - $shownWidth, 2);
    $top = \intdiv($height - $shownHeight, 2);

    ImageBarrier::transition($vk, $cmd, $target->image, VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED, VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL);
    $vk->vkCmdClearColorImage(
        $cmd,
        $target->image,
        VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL,
        new VkClearColorValue(float32: [0.0, 0.0, 0.0, 1.0]),
        [new VkImageSubresourceRange(VkImageAspectFlagBits::VK_IMAGE_ASPECT_COLOR_BIT, 0, 1, 0, 1)],
    );
    $vk->vkCmdBlitImage(
        $cmd,
        $canvas->handle,
        VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_SRC_OPTIMAL,
        $target->image,
        VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL,
        [new VkImageBlit(
            new VkImageSubresourceLayers(VkImageAspectFlagBits::VK_IMAGE_ASPECT_COLOR_BIT, 0, 0, 1),
            [new VkOffset3D(0, 0, 0), new VkOffset3D(RENDER_WIDTH, RENDER_HEIGHT, 1)],
            new VkImageSubresourceLayers(VkImageAspectFlagBits::VK_IMAGE_ASPECT_COLOR_BIT, 0, 0, 1),
            [new VkOffset3D($left, $top, 0), new VkOffset3D($left + $shownWidth, $top + $shownHeight, 1)],
        )],
        VkFilter::VK_FILTER_NEAREST,
    );
    ImageBarrier::transition($vk, $cmd, $target->image, VkImageLayout::VK_IMAGE_LAYOUT_TRANSFER_DST_OPTIMAL, VkImageLayout::VK_IMAGE_LAYOUT_PRESENT_SRC_KHR);
});

$frames = 0;
$total = 0;
$since = $window->getTime();
$limit = (float) ($argv[2] ?? 0);

while (!$window->shouldClose() && !$window->isKeyDown(Key::ESCAPE) && ($limit === 0.0 || $window->getTime() < $limit)) {
    $window->pollEvents();
    $frame = $loop->beginFrame();

    if ($frame === null) {
        $window->waitEvents(0.01);

        continue;
    }

    // All the host does for a frame: two small buffers.
    $time = $window->getTime();
    $data = '';

    for ($i = 0; $i < QUADS; ++$i) {
        $angle = $time * 0.6 + $i * (2 * \M_PI / QUADS);
        $data .= \pack('f4V4', 0.7 * \cos($angle) - 0.1, 0.7 * \sin($angle) - 0.15, 0.2, 0.3, $i, (int) ($time * 30) & 255, 0, 0);
    }

    $instances[$frame->index]->write($data);
    $indirect[$frame->index]->write(\pack('V4', 6, QUADS, 0, 0));

    $loop->endFrame($frame);

    ++$frames;
    ++$total;

    if ($time - $since >= 1.0) {
        $window->setTitle(\sprintf('local/gpu demo, %d fps, php %.1f MB', $frames, \memory_get_usage() / 1e6));
        $frames = 0;
        $since = $time;
    }
}

$loop->waitIdle();

echo \sprintf("frames: %d, php memory: %.1f MB
", $total, \memory_get_peak_usage() / 1e6);
