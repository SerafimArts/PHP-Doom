<?php

declare(strict_types=1);

namespace Local\GPU\Tests;

use Local\GPU\Device;
use Local\GPU\Exception\ResourceException;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Resource\ImageInfo;
use Local\GPU\Resource\Transfer;
use Local\GPU\Tests\Support\QuadsScene;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageUsageFlagBits;

#[Group('gpu')]
#[CoversClass(Device::class)]
#[CoversClass(Transfer::class)]
final class GpuTest extends TestCase
{
    public function testHostVisibleBufferKeepsWrittenBytes(): void
    {
        $device = $this->createDevice();
        $buffer = $device->createBuffer(
            16,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT,
            MemoryKind::HOST_VISIBLE,
        );

        $buffer->write('abcdef', 4);

        self::assertSame("\0\0\0\0abcdef", $buffer->read(10));
        $this->assertNoValidationMessages();
    }

    public function testWritingBeyondTheBufferThrowsResourceException(): void
    {
        $device = $this->createDevice();
        $buffer = $device->createBuffer(
            4,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT,
            MemoryKind::HOST_VISIBLE,
        );

        $this->expectException(ResourceException::class);

        $buffer->write('12345');
    }

    public function testDeviceLocalBufferIsFilledThroughStagingAndStreamingBufferIsMapped(): void
    {
        $device = $this->createDevice();
        $transfer = new Transfer($device);

        $local = $device->createBuffer(
            8,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT
                | VkBufferUsageFlagBits::VK_BUFFER_USAGE_TRANSFER_DST_BIT,
            MemoryKind::DEVICE_LOCAL,
        );
        $transfer->uploadBuffer($local, '12345678');

        $streaming = $device->createBuffer(
            8,
            VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT,
            MemoryKind::STREAMING,
        );

        self::assertFalse($local->isMapped());
        self::assertTrue($streaming->isMapped());
        $this->assertNoValidationMessages();
    }

    public function testImageUploadAndDownloadRoundTrip(): void
    {
        $device = $this->createDevice();
        $transfer = new Transfer($device);

        $image = $device->createImage(new ImageInfo(
            4,
            2,
            VkFormat::VK_FORMAT_R8_UNORM,
            VkImageUsageFlagBits::VK_IMAGE_USAGE_SAMPLED_BIT
                | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_DST_BIT
                | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_SRC_BIT,
        ));

        $pixels = "\x01\x02\x03\x04\x05\x06\x07\x08";
        $transfer->uploadImage($image, $pixels, 1);

        self::assertSame($pixels, $transfer->downloadImage($image, 1));
        $this->assertNoValidationMessages();
    }

    public function testIndexedColorQuadsAreDrawnWithTheirPaletteColors(): void
    {
        $device = $this->createDevice();
        $scene = new QuadsScene($device, new Transfer($device), \dirname(__DIR__) . '/examples/shaders');

        $pixels = $scene->render([
            ['x' => -1.0, 'y' => -1.0, 'width' => 1.0, 'height' => 1.0, 'tile' => 3, 'shift' => 5],
            ['x' => 0.0, 'y' => 0.0, 'width' => 1.0, 'height' => 1.0, 'tile' => 15, 'shift' => 0],
        ]);

        $at = static fn(int $x, int $y): array => \array_values(
            \unpack('C4', $pixels, ($y * QuadsScene::SIZE + $x) * 4),
        );

        self::assertSame([54, 0, 201, 255], $at(16, 16), 'top-left quadrant: tile 3 (index 49) shifted by 5');
        self::assertSame([241, 0, 14, 255], $at(48, 48), 'bottom-right quadrant: tile 15 (index 241)');
        self::assertSame([0, 255, 0, 255], $at(48, 16), 'untouched area keeps the clear color');
        $this->assertNoValidationMessages();
    }
}
