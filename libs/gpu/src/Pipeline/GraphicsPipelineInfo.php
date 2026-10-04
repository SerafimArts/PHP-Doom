<?php

declare(strict_types=1);

namespace Local\GPU\Pipeline;

use Serafim\Vulkan\Enum\VkCompareOp;
use Serafim\Vulkan\Enum\VkCullModeFlagBits;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkFrontFace;
use Serafim\Vulkan\Enum\VkPrimitiveTopology;
use Serafim\Vulkan\Handle\VkDescriptorSetLayout;
use Serafim\Vulkan\Struct\VkPushConstantRange;
use Serafim\Vulkan\Struct\VkVertexInputAttributeDescription;
use Serafim\Vulkan\Struct\VkVertexInputBindingDescription;

/**
 * Description of a graphics pipeline for dynamic rendering. The viewport and the scissor are always dynamic. Unlike the
 * structures of Vulkan the defaults are the ones that are usually wanted.
 */
final readonly class GraphicsPipelineInfo
{
    public function __construct(
        /**
         * SPIR-V of the vertex shader
         *
         * @var non-empty-string
         */
        public string $vertexShader,
        /**
         * SPIR-V of the fragment shader
         *
         * @var non-empty-string
         */
        public string $fragmentShader,
        /** @var list<VkFormat> */
        public array $colorFormats,
        /** @var list<VkVertexInputBindingDescription> */
        public array $vertexBindings = [],
        /** @var list<VkVertexInputAttributeDescription> */
        public array $vertexAttributes = [],
        public VkPrimitiveTopology $topology = VkPrimitiveTopology::VK_PRIMITIVE_TOPOLOGY_TRIANGLE_LIST,
        /**
         * Faces to cull, flags of `VkCullModeFlagBits`
         *
         * @var int<0, 3>
         */
        public int $cullMode = VkCullModeFlagBits::VK_CULL_MODE_NONE,
        public VkFrontFace $frontFace = VkFrontFace::VK_FRONT_FACE_COUNTER_CLOCKWISE,
        public ?VkFormat $depthFormat = null,
        public bool $isDepthTestEnabled = false,
        public bool $isDepthWriteEnabled = false,
        public VkCompareOp $depthCompareOp = VkCompareOp::VK_COMPARE_OP_LESS,
        public bool $isBlendEnabled = false,
        /** @var list<VkDescriptorSetLayout> */
        public array $setLayouts = [],
        /** @var list<VkPushConstantRange> */
        public array $pushConstantRanges = [],
    ) {}
}
