<?php

declare(strict_types=1);

namespace Local\GPU\Pipeline;

use Local\GPU\Device;
use Local\GPU\Exception\ResourceException;
use Serafim\Vulkan\Enum\VkBlendFactor;
use Serafim\Vulkan\Enum\VkBlendOp;
use Serafim\Vulkan\Enum\VkColorComponentFlagBits;
use Serafim\Vulkan\Enum\VkDynamicState;
use Serafim\Vulkan\Enum\VkFormat;
use Serafim\Vulkan\Enum\VkSampleCountFlagBits;
use Serafim\Vulkan\Enum\VkShaderStageFlagBits;
use Serafim\Vulkan\Handle\VkPipeline;
use Serafim\Vulkan\Handle\VkPipelineLayout;
use Serafim\Vulkan\Struct\VkComputePipelineCreateInfo;
use Serafim\Vulkan\Struct\VkGraphicsPipelineCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineColorBlendAttachmentState;
use Serafim\Vulkan\Struct\VkPipelineColorBlendStateCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineDepthStencilStateCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineDynamicStateCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineInputAssemblyStateCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineLayoutCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineMultisampleStateCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineRasterizationStateCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineRenderingCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineShaderStageCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineVertexInputStateCreateInfo;
use Serafim\Vulkan\Struct\VkPipelineViewportStateCreateInfo;
use Serafim\Vulkan\Struct\VkShaderModuleCreateInfo;
use Serafim\Vulkan\Struct\VkStencilOpState;

final readonly class Pipeline
{
    private function __construct(
        public VkPipeline $handle,
        public VkPipelineLayout $layout,
    ) {}

    /**
     * @throws ResourceException in case of the description is inconsistent
     */
    public static function createGraphics(Device $device, GraphicsPipelineInfo $info): self
    {
        $vk = $device->vk;

        // @phpstan-ignore method.deprecated (descriptor sets and push constants are the only model used here)
        $layout = $vk->vkCreatePipelineLayout($device->device, new VkPipelineLayoutCreateInfo(
            setLayouts: $info->setLayouts,
            pushConstantRanges: $info->pushConstantRanges,
        ));

        $vertex = $vk->vkCreateShaderModule($device->device, new VkShaderModuleCreateInfo(code: $info->vertexShader));
        $fragment = $vk->vkCreateShaderModule(
            $device->device,
            new VkShaderModuleCreateInfo(code: $info->fragmentShader),
        );

        $write = VkColorComponentFlagBits::VK_COLOR_COMPONENT_R_BIT
            | VkColorComponentFlagBits::VK_COLOR_COMPONENT_G_BIT
            | VkColorComponentFlagBits::VK_COLOR_COMPONENT_B_BIT
            | VkColorComponentFlagBits::VK_COLOR_COMPONENT_A_BIT;

        $attachment = new VkPipelineColorBlendAttachmentState(colorWriteMask: $write);

        if ($info->isBlendEnabled) {
            $attachment = new VkPipelineColorBlendAttachmentState(
                blendEnable: true,
                srcColorBlendFactor: VkBlendFactor::VK_BLEND_FACTOR_SRC_ALPHA,
                dstColorBlendFactor: VkBlendFactor::VK_BLEND_FACTOR_ONE_MINUS_SRC_ALPHA,
                colorBlendOp: VkBlendOp::VK_BLEND_OP_ADD,
                srcAlphaBlendFactor: VkBlendFactor::VK_BLEND_FACTOR_ONE,
                dstAlphaBlendFactor: VkBlendFactor::VK_BLEND_FACTOR_ZERO,
                alphaBlendOp: VkBlendOp::VK_BLEND_OP_ADD,
                colorWriteMask: $write,
            );
        }

        $depthFormat = $info->depthFormat ?? VkFormat::VK_FORMAT_UNDEFINED;

        [$pipeline] = $vk->vkCreateGraphicsPipelines($device->device, [new VkGraphicsPipelineCreateInfo(
            chain: [new VkPipelineRenderingCreateInfo(
                colorAttachmentFormats: $info->colorFormats,
                depthAttachmentFormat: $depthFormat,
            )],
            stages: [
                new VkPipelineShaderStageCreateInfo(
                    stage: VkShaderStageFlagBits::VK_SHADER_STAGE_VERTEX_BIT,
                    module: $vertex,
                    name: 'main',
                ),
                new VkPipelineShaderStageCreateInfo(
                    stage: VkShaderStageFlagBits::VK_SHADER_STAGE_FRAGMENT_BIT,
                    module: $fragment,
                    name: 'main',
                ),
            ],
            vertexInputState: new VkPipelineVertexInputStateCreateInfo(
                vertexBindingDescriptions: $info->vertexBindings,
                vertexAttributeDescriptions: $info->vertexAttributes,
            ),
            inputAssemblyState: new VkPipelineInputAssemblyStateCreateInfo(topology: $info->topology),
            viewportState: new VkPipelineViewportStateCreateInfo(viewportCount: 1, scissorCount: 1),
            rasterizationState: new VkPipelineRasterizationStateCreateInfo(
                cullMode: $info->cullMode,
                frontFace: $info->frontFace,
                lineWidth: 1.0,
            ),
            multisampleState: new VkPipelineMultisampleStateCreateInfo(
                rasterizationSamples: VkSampleCountFlagBits::VK_SAMPLE_COUNT_1_BIT,
            ),
            depthStencilState: new VkPipelineDepthStencilStateCreateInfo(
                front: new VkStencilOpState(),
                back: new VkStencilOpState(),
                depthTestEnable: $info->isDepthTestEnabled,
                depthWriteEnable: $info->isDepthWriteEnabled,
                depthCompareOp: $info->depthCompareOp,
                maxDepthBounds: 1.0,
            ),
            colorBlendState: new VkPipelineColorBlendStateCreateInfo(
                attachments: \array_fill(0, \max(1, \count($info->colorFormats)), $attachment),
            ),
            dynamicState: new VkPipelineDynamicStateCreateInfo(dynamicStates: [
                VkDynamicState::VK_DYNAMIC_STATE_VIEWPORT,
                VkDynamicState::VK_DYNAMIC_STATE_SCISSOR,
            ]),
            layout: $layout,
        )]);

        return new self($pipeline, $layout);
    }

    public static function createCompute(Device $device, ComputePipelineInfo $info): self
    {
        $vk = $device->vk;

        // @phpstan-ignore method.deprecated (descriptor sets and push constants are the only model used here)
        $layout = $vk->vkCreatePipelineLayout($device->device, new VkPipelineLayoutCreateInfo(
            setLayouts: $info->setLayouts,
            pushConstantRanges: $info->pushConstantRanges,
        ));

        $module = $vk->vkCreateShaderModule($device->device, new VkShaderModuleCreateInfo(code: $info->shader));

        [$pipeline] = $vk->vkCreateComputePipelines($device->device, [new VkComputePipelineCreateInfo(
            stage: new VkPipelineShaderStageCreateInfo(
                stage: VkShaderStageFlagBits::VK_SHADER_STAGE_COMPUTE_BIT,
                module: $module,
                name: 'main',
            ),
            layout: $layout,
        )]);

        return new self($pipeline, $layout);
    }
}
