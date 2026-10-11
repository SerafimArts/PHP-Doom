<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Gpu;

use Local\GPU\Device;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Resource\Buffer;
use Local\GPU\Resource\Image;
use Local\GPU\Resource\ImageInfo;
use Local\GPU\Resource\Transfer;
use Local\WAD\Image\Colormap;
use Local\WAD\Image\Palette;
use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Layout\PaletteTable;
use Serafim\Doom\Render\Layout\SpriteViewTable;
use Serafim\Doom\Render\Mesh\LevelMesh;
use Serafim\Doom\Render\Sprite\SpriteSet;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageUsageFlagBits;

/**
 * What the level needs on the GPU and never changes: the atlas, the colormap and the palettes as textures, the tables
 * of the pictures, the walls and the sprite views, and the vertices of the floors and the ceilings.
 */
final readonly class StaticResources
{
    private function __construct(
        public Image $atlas,
        public Image $colormap,
        public Image $palettes,
        public Buffer $rects,
        public Buffer $walls,
        public Buffer $spriteViews,
        public Buffer $flatVertices,
    ) {}

    /**
     * @param list<Palette> $palettes
     */
    public static function create(
        Device $device,
        Transfer $transfer,
        TextureAtlas $atlas,
        array $palettes,
        Colormap $colormap,
        SpriteSet $sprites,
        LevelMesh $mesh,
    ): self {
        $storage = VkBufferUsageFlagBits::VK_BUFFER_USAGE_STORAGE_BUFFER_BIT;

        return new self(
            self::createTexture(
                $device,
                $transfer,
                $atlas->width,
                $atlas->height,
                VkFormat::VK_FORMAT_R8G8_UNORM,
                $atlas->pixels,
                2,
            ),
            self::createTexture(
                $device,
                $transfer,
                256,
                \min(4_294_967_295, \max(1, \count($colormap->rows))),
                VkFormat::VK_FORMAT_R8_UNORM,
                \implode('', $colormap->rows),
                1,
            ),
            self::createTexture(
                $device,
                $transfer,
                256,
                \min(4_294_967_295, \max(1, \count($palettes))),
                VkFormat::VK_FORMAT_R8G8B8A8_UNORM,
                PaletteTable::pack($palettes),
                4,
            ),
            self::createBuffer($device, $transfer, $atlas->packRects(), $storage),
            self::createBuffer($device, $transfer, $mesh->walls, $storage),
            self::createBuffer($device, $transfer, SpriteViewTable::pack($atlas, $sprites), $storage),
            self::createBuffer(
                $device,
                $transfer,
                $mesh->flatVertices,
                VkBufferUsageFlagBits::VK_BUFFER_USAGE_VERTEX_BUFFER_BIT,
            ),
        );
    }

    /**
     * @param int<1, 4294967295> $width
     * @param int<1, 4294967295> $height
     * @param int<1, max> $bytesPerPixel
     */
    private static function createTexture(
        Device $device,
        Transfer $transfer,
        int $width,
        int $height,
        VkFormat $format,
        string $pixels,
        int $bytesPerPixel,
    ): Image {
        $image = $device->createImage(new ImageInfo(
            $width,
            $height,
            $format,
            VkImageUsageFlagBits::VK_IMAGE_USAGE_SAMPLED_BIT | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_DST_BIT,
        ));

        $transfer->uploadImage($image, $pixels, $bytesPerPixel);

        return $image;
    }

    /**
     * @param int<0, 4294967295> $usage
     */
    private static function createBuffer(Device $device, Transfer $transfer, string $data, int $usage): Buffer
    {
        $buffer = $device->createBuffer(
            \max(4, \strlen($data)),
            $usage | VkBufferUsageFlagBits::VK_BUFFER_USAGE_TRANSFER_DST_BIT,
            MemoryKind::DEVICE_LOCAL,
        );

        $transfer->uploadBuffer($buffer, $data);

        return $buffer;
    }
}
