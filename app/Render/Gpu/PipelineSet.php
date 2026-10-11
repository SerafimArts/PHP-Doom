<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Gpu;

use Local\GPU\Device;
use Local\GPU\Pipeline\GraphicsPipelineInfo;
use Local\GPU\Pipeline\Pipeline;
use Serafim\Doom\Render\Mesh\LevelMesh;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkVertexInputRate;
use Serafim\Vulkan\Handle\VkDescriptorSetLayout;
use Serafim\Vulkan\Struct\VkVertexInputAttributeDescription;
use Serafim\Vulkan\Struct\VkVertexInputBindingDescription;

/**
 * The pipelines that draw a level into the canvas, in the order of the drawing.
 */
final readonly class PipelineSet
{
    private function __construct(
        /**
         * Fills everything that nothing else covers
         */
        public Pipeline $sky,
        public Pipeline $flats,
        public Pipeline $walls,
        public Pipeline $sprites,
        public Pipeline $hud,
    ) {}

    /**
     * @param bool $isRayLit the fragment shaders of the walls, the floors and the sprites are the ones that trace rays
     */
    public static function create(
        Device $device,
        ShaderLoader $shaders,
        VkDescriptorSetLayout $layout,
        VkFormat $colorFormat,
        VkFormat $depthFormat,
        bool $isRayLit,
    ): self {
        $fragment = '.spv';

        if ($isRayLit) {
            $fragment = '.rt.spv';
        }

        $create = static fn(string $name, string $fragmentSuffix, bool $isDepthTested, array $vertex = []) =>
            Pipeline::createGraphics($device, new GraphicsPipelineInfo(
                vertexShader: $shaders->load($name . '.vert.spv'),
                fragmentShader: $shaders->load($name . '.frag' . $fragmentSuffix),
                colorFormats: [$colorFormat],
                vertexBindings: $vertex['bindings'] ?? [],
                vertexAttributes: $vertex['attributes'] ?? [],
                depthFormat: $depthFormat,
                isDepthTestEnabled: $isDepthTested,
                isDepthWriteEnabled: $isDepthTested,
                setLayouts: [$layout],
            ));

        return new self(
            sky: $create('sky', '.spv', false),
            flats: $create('flat', $fragment, true, [
                'bindings' => [new VkVertexInputBindingDescription(
                    0,
                    LevelMesh::FLAT_VERTEX_SIZE,
                    VkVertexInputRate::VK_VERTEX_INPUT_RATE_VERTEX,
                )],
                'attributes' => [
                    new VkVertexInputAttributeDescription(0, 0, VkFormat::VK_FORMAT_R32G32_SFLOAT, 0),
                    new VkVertexInputAttributeDescription(1, 0, VkFormat::VK_FORMAT_R32_UINT, 8),
                ],
            ]),
            walls: $create('wall', $fragment, true),
            sprites: $create('sprite', $fragment, true),
            hud: $create('hud', '.spv', false),
        );
    }
}
