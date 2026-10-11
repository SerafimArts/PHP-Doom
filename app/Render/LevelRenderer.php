<?php

declare(strict_types=1);

namespace Serafim\Doom\Render;

use Local\GPU\Device;
use Local\GPU\Frame\FrameLoop;
use Local\GPU\Resource\Buffer;
use Local\GPU\Resource\Image;
use Local\GPU\Resource\ImageInfo;
use Local\GPU\Resource\Transfer;
use Local\GPU\Sync\ImageBarrier;
use Local\WAD\Image\Colormap;
use Local\WAD\Image\Palette;
use Serafim\Doom\Exception\AtlasException;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LevelState;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Data\Limits;
use Serafim\Doom\Render\Gpu\FrameResources;
use Serafim\Doom\Render\Gpu\LevelDescriptors;
use Serafim\Doom\Render\Gpu\PipelineSet;
use Serafim\Doom\Render\Gpu\ShaderLoader;
use Serafim\Doom\Render\Gpu\StaticResources;
use Serafim\Doom\Render\Layout\DrawCommand;
use Serafim\Doom\Render\Layout\WallPictureTable;
use Serafim\Doom\Render\Mesh\LevelMesh;
use Serafim\Doom\Render\Mesh\LevelMeshBuilder;
use Serafim\Doom\Render\Raytracing\ShadowScene;
use Serafim\Doom\Render\Sprite\SpriteSet;
use Serafim\Vulkan\Enum\VkAttachmentLoadOp;
use Serafim\Vulkan\Enum\VkAttachmentStoreOp;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkImageAspectFlagBits;
use Serafim\Vulkan\Enum\VkImageLayout;
use Serafim\Vulkan\Enum\VkImageUsageFlagBits;
use Serafim\Vulkan\Enum\VkPipelineBindPoint;
use Serafim\Vulkan\Handle\VkCommandBuffer;
use Serafim\Vulkan\Struct\VkClearColorValue;
use Serafim\Vulkan\Struct\VkClearDepthStencilValue;
use Serafim\Vulkan\Struct\VkClearValue;
use Serafim\Vulkan\Struct\VkExtent2D;
use Serafim\Vulkan\Struct\VkOffset2D;
use Serafim\Vulkan\Struct\VkRect2D;
use Serafim\Vulkan\Struct\VkRenderingAttachmentInfo;
use Serafim\Vulkan\Struct\VkRenderingInfo;
use Serafim\Vulkan\Struct\VkViewport;

/**
 * Draws a level into an image of its own (the canvas). The host only writes the camera and the table of sectors before
 * a frame, the shaders do the rest. The commands are recorded once per frame in flight ({@see self::record()}).
 */
final class LevelRenderer
{
    public const VkFormat CANVAS_FORMAT = VkFormat::VK_FORMAT_R8G8B8A8_UNORM;

    private const VkFormat DEPTH_FORMAT = VkFormat::VK_FORMAT_D32_SFLOAT;

    public readonly Image $canvas;

    /**
     * The lamps, the flashes and the fireballs light the level with shadows. The GPU needs ray queries for it, a device
     * without them lights the level with the plain light of the sectors whatever this is.
     */
    public bool $isRayLightEnabled = true;

    private readonly SectorTable $sectorTable;

    private readonly Image $depth;

    /** @var int<0, max> */
    private readonly int $skyPicture;

    private readonly LevelMesh $mesh;

    private readonly ?ShadowScene $shadows;

    private readonly WallPictureTable $wallPictures;

    private readonly StaticResources $resources;

    private readonly PipelineSet $pipelines;

    /** @var list<FrameResources> */
    private array $frames = [];

    /**
     * @param list<Palette> $palettes
     *
     * @throws AtlasException in case of the atlas misses a texture of the level
     */
    public function __construct(
        private readonly Device $device,
        private readonly Transfer $transfer,
        Level $level,
        TextureAtlas $atlas,
        array $palettes,
        Colormap $colormap,
        SpriteSet $spriteSet,
        private readonly Limits $limits,
        string $shaderDirectory,
        /** @var int<1, 4294967295> */
        public readonly int $width = 640,
        /** @var int<1, 4294967295> */
        public readonly int $height = 400,
    ) {
        $this->mesh = new LevelMeshBuilder()->build($level, $atlas);
        $this->shadows = $this->createShadows($level);
        $this->sectorTable = SectorTable::create(\count($level->map->sectors), $atlas);
        $this->wallPictures = new WallPictureTable($level->geometry->walls, $atlas);
        $this->skyPicture = $atlas->getNumber(LevelAtlasFactory::getTextureKey(LevelAtlasFactory::SKY_TEXTURE));

        $this->canvas = $device->createImage(new ImageInfo(
            $width,
            $height,
            self::CANVAS_FORMAT,
            VkImageUsageFlagBits::VK_IMAGE_USAGE_COLOR_ATTACHMENT_BIT
                | VkImageUsageFlagBits::VK_IMAGE_USAGE_TRANSFER_SRC_BIT,
        ));
        $this->depth = $device->createImage(new ImageInfo(
            $width,
            $height,
            self::DEPTH_FORMAT,
            VkImageUsageFlagBits::VK_IMAGE_USAGE_DEPTH_STENCIL_ATTACHMENT_BIT,
            aspect: VkImageAspectFlagBits::VK_IMAGE_ASPECT_DEPTH_BIT,
        ));

        $this->resources = StaticResources::create(
            $device,
            $transfer,
            $atlas,
            $palettes,
            $colormap,
            $spriteSet,
            $this->mesh,
        );

        $descriptors = new LevelDescriptors($device, $this->shadows !== null);

        for ($i = 0; $i < FrameLoop::FRAMES_IN_FLIGHT; ++$i) {
            $this->frames[] = $descriptors->createFrame(
                $i,
                $this->resources,
                $this->sectorTable->count(),
                $this->wallPictures->count(),
                $this->shadows,
            );
        }

        $this->pipelines = PipelineSet::create(
            $device,
            new ShaderLoader($shaderDirectory),
            $descriptors->layout->handle,
            self::CANVAS_FORMAT,
            self::DEPTH_FORMAT,
            $this->shadows !== null,
        );
    }

    /**
     * The device has ray queries: the lamps and the flashes light the level with shadows while {@see
     * self::$isRayLightEnabled}.
     */
    public function hasRayLighting(): bool
    {
        return $this->shadows !== null;
    }

    /**
     * Flips the ray lighting and tells the new state, or that the device cannot trace rays.
     */
    public function toggleRayLight(): string
    {
        if ($this->shadows === null) {
            return 'this GPU has no ray queries';
        }

        $this->isRayLightEnabled = !$this->isRayLightEnabled;

        return \sprintf('ray lighting %s', $this->describeRayLight());
    }

    /**
     * The lighting in use: `plain light` without ray queries, otherwise `rays on` or `rays off`.
     */
    public function describeLighting(): string
    {
        if ($this->shadows === null) {
            return 'plain light';
        }

        return \sprintf('rays %s', $this->describeRayLight());
    }

    /**
     * @param int<0, max> $frameIndex
     * @param int<0, max> $palette the palette of the screen tint (0 is the normal one)
     */
    public function update(
        int $frameIndex,
        Camera $camera,
        LevelState $state,
        float $time = 0.0,
        int $palette = 0,
        Scene $scene = new Scene(),
    ): void {
        $frame = $this->frames[$frameIndex];

        $this->writeIfAny($frame->sprites, $scene->sprites);
        $this->writeIfAny($frame->quads, $scene->quads);
        $frame->draw->write(DrawCommand::pack($scene->spriteCount, $scene->quadCount));

        $frame->frame->write(
            $camera->packFrame(
                $time,
                $palette,
                $this->width,
                $this->height,
                $this->skyPicture,
                $scene->extraLight,
                $this->shadows !== null && $this->isRayLightEnabled,
            ),
        );
        $this->shadows?->update($frameIndex, $state->sectors, $scene->casters, $scene->lights);
        $frame->sectors->write($this->sectorTable->pack($state->sectors));

        if ($this->wallPictures->count() > 0) {
            $frame->pictures->write($this->wallPictures->pack($state->sides));
        }
    }

    /**
     * Records the drawing of the level into the canvas. The canvas ends up in the layout `COLOR_ATTACHMENT_OPTIMAL`.
     *
     * @param int<0, max> $frameIndex
     */
    public function record(VkCommandBuffer $commandBuffer, int $frameIndex): void
    {
        $vk = $this->device->vk;
        $area = new VkRect2D(new VkOffset2D(0, 0), new VkExtent2D($this->width, $this->height));

        $this->shadows?->record($vk, $commandBuffer, $frameIndex);

        ImageBarrier::transition(
            $vk,
            $commandBuffer,
            $this->canvas->handle,
            VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED,
            VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL,
        );
        ImageBarrier::transition(
            $vk,
            $commandBuffer,
            $this->depth->handle,
            VkImageLayout::VK_IMAGE_LAYOUT_UNDEFINED,
            VkImageLayout::VK_IMAGE_LAYOUT_DEPTH_STENCIL_ATTACHMENT_OPTIMAL,
            VkImageAspectFlagBits::VK_IMAGE_ASPECT_DEPTH_BIT,
        );

        $vk->vkCmdBeginRendering($commandBuffer, new VkRenderingInfo(
            renderArea: $area,
            layerCount: 1,
            colorAttachments: [new VkRenderingAttachmentInfo(
                clearValue: new VkClearValue(color: new VkClearColorValue(float32: [0.0, 0.0, 0.0, 1.0])),
                imageView: $this->canvas->view,
                imageLayout: VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL,
                loadOp: VkAttachmentLoadOp::VK_ATTACHMENT_LOAD_OP_CLEAR,
                storeOp: VkAttachmentStoreOp::VK_ATTACHMENT_STORE_OP_STORE,
            )],
            depthAttachment: new VkRenderingAttachmentInfo(
                clearValue: new VkClearValue(depthStencil: new VkClearDepthStencilValue(1.0, 0)),
                imageView: $this->depth->view,
                imageLayout: VkImageLayout::VK_IMAGE_LAYOUT_DEPTH_STENCIL_ATTACHMENT_OPTIMAL,
                loadOp: VkAttachmentLoadOp::VK_ATTACHMENT_LOAD_OP_CLEAR,
                storeOp: VkAttachmentStoreOp::VK_ATTACHMENT_STORE_OP_DONT_CARE,
            ),
        ));

        $vk->vkCmdSetViewport(
            $commandBuffer,
            0,
            [new VkViewport(0.0, 0.0, (float) $this->width, (float) $this->height, 0.0, 1.0)],
        );
        $vk->vkCmdSetScissor($commandBuffer, 0, [$area]);

        $graphics = VkPipelineBindPoint::VK_PIPELINE_BIND_POINT_GRAPHICS;
        $descriptors = [$this->frames[$frameIndex]->descriptors->handle];

        // The sky fills everything that nothing else covers: above the walls, through the windows.
        $vk->vkCmdBindPipeline($commandBuffer, $graphics, $this->pipelines->sky->handle);
        // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
        $vk->vkCmdBindDescriptorSets($commandBuffer, $graphics, $this->pipelines->sky->layout, 0, $descriptors, []);
        $vk->vkCmdDraw($commandBuffer, 3, 1, 0, 0);

        if ($this->mesh->flatVertexCount > 0) {
            $vk->vkCmdBindPipeline($commandBuffer, $graphics, $this->pipelines->flats->handle);
            // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
            $vk->vkCmdBindDescriptorSets(
                $commandBuffer,
                $graphics,
                $this->pipelines->flats->layout,
                0,
                $descriptors,
                [],
            );
            $vk->vkCmdBindVertexBuffers($commandBuffer, 0, [$this->resources->flatVertices->handle], [0]);
            $vk->vkCmdDraw($commandBuffer, $this->mesh->flatVertexCount, 2, 0, 0);
        }

        if ($this->mesh->wallCount > 0) {
            $vk->vkCmdBindPipeline($commandBuffer, $graphics, $this->pipelines->walls->handle);
            // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
            $vk->vkCmdBindDescriptorSets(
                $commandBuffer,
                $graphics,
                $this->pipelines->walls->layout,
                0,
                $descriptors,
                [],
            );
            $vk->vkCmdDraw($commandBuffer, 6, $this->mesh->wallCount, 0, 0);
        }

        $vk->vkCmdBindPipeline($commandBuffer, $graphics, $this->pipelines->sprites->handle);
        // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
        $vk->vkCmdBindDescriptorSets($commandBuffer, $graphics, $this->pipelines->sprites->layout, 0, $descriptors, []);
        // @phpstan-ignore method.deprecated (the draw with the number of sprites that the host writes for a frame)
        $vk->vkCmdDrawIndirect($commandBuffer, $this->frames[$frameIndex]->draw->handle, 0, 1, DrawCommand::SIZE);

        $vk->vkCmdBindPipeline($commandBuffer, $graphics, $this->pipelines->hud->handle);
        // @phpstan-ignore method.deprecated (descriptor sets are the only descriptor model used here)
        $vk->vkCmdBindDescriptorSets($commandBuffer, $graphics, $this->pipelines->hud->layout, 0, $descriptors, []);
        // @phpstan-ignore method.deprecated (the draw with the number of pictures that the host writes for a frame)
        $vk->vkCmdDrawIndirect(
            $commandBuffer,
            $this->frames[$frameIndex]->draw->handle,
            DrawCommand::QUADS_OFFSET,
            1,
            DrawCommand::SIZE,
        );

        $vk->vkCmdEndRendering($commandBuffer);
    }

    /**
     * Draws one frame and returns the RGBA pixels of the canvas (without a window, for tests and screenshots).
     */
    public function render(Camera $camera, LevelState $state, float $time = 0.0, Scene $scene = new Scene()): string
    {
        $this->update(0, $camera, $state, $time, 0, $scene);
        $this->transfer->submit(fn(VkCommandBuffer $commandBuffer) => $this->record($commandBuffer, 0));
        $this->canvas->layout = VkImageLayout::VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL;

        return $this->transfer->downloadImage($this->canvas, 4);
    }

    private function writeIfAny(Buffer $buffer, string $data): void
    {
        if ($data !== '') {
            $buffer->write($data);
        }
    }

    private function describeRayLight(): string
    {
        if ($this->isRayLightEnabled) {
            return 'on';
        }

        return 'off';
    }

    private function createShadows(Level $level): ?ShadowScene
    {
        if (!$this->device->isRayQueryEnabled) {
            return null;
        }

        return ShadowScene::create(
            $this->device,
            $this->transfer,
            $level,
            $this->mesh,
            $this->limits->maxLights,
        );
    }
}
