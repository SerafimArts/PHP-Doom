<?php

declare(strict_types=1);

namespace Local\GPU\Pipeline;

use Serafim\Vulkan\Handle\VkDescriptorSetLayout;
use Serafim\Vulkan\Struct\VkPushConstantRange;

/**
 * Description of a compute pipeline: the shader and what it binds.
 */
final readonly class ComputePipelineInfo
{
    public function __construct(
        /**
         * SPIR-V of the compute shader
         *
         * @var non-empty-string
         */
        public string $shader,
        /** @var list<VkDescriptorSetLayout> */
        public array $setLayouts = [],
        /** @var list<VkPushConstantRange> */
        public array $pushConstantRanges = [],
    ) {}
}
