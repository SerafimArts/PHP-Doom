<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use Local\GPU\DeviceOptions;
use Local\GPU\Window\Window;
use Serafim\Vulkan\Enum\VkInstanceCreateFlagBits;
use Serafim\Vulkan\Handle\VkPhysicalDevice;
use Serafim\Vulkan\Struct\Extension\VkDeviceCreateInfoExtension;
use Serafim\Vulkan\Struct\VkPhysicalDeviceAccelerationStructureFeaturesKHR;
use Serafim\Vulkan\Struct\VkPhysicalDeviceRayQueryFeaturesKHR;
use Serafim\Vulkan\Struct\VkPhysicalDeviceVulkan13Features;
use Serafim\Vulkan\Vulkan;

/**
 * What the instance and the device of the library ask for: the layers and the extensions, and the features of
 * Vulkan 1.3 that the renderer relies on.
 */
final readonly class Requirements
{
    /**
     * The device extensions of ray queries
     */
    public const array RAY_QUERY_EXTENSIONS = [
        'VK_KHR_acceleration_structure',
        'VK_KHR_deferred_host_operations',
        'VK_KHR_ray_query',
    ];

    /**
     * Implementations on top of another API (MoltenVK) are hidden by the loader unless the instance asks for them
     */
    private const string PORTABILITY_ENUMERATION_EXTENSION = 'VK_KHR_portability_enumeration';

    /**
     * Such a device has to enable the extension, otherwise it is not conformant
     */
    private const string PORTABILITY_SUBSET_EXTENSION = 'VK_KHR_portability_subset';

    public function __construct(
        /** @var list<non-empty-string> */
        public array $layers,
        /** @var list<non-empty-string> */
        public array $instanceExtensions,
        /** @var list<non-empty-string> */
        public array $deviceExtensions,
        public bool $isRayQueryRequired,
        /** @var int-mask-of<VkInstanceCreateFlagBits::*> */
        public int $instanceFlags = 0,
    ) {}

    public static function createFromOptions(Vulkan $vk, ?Window $window, DeviceOptions $options): self
    {
        $layers = [];
        $instanceExtensions = $window?->getRequiredInstanceExtensions() ?? [];
        $deviceExtensions = [];
        $instanceFlags = 0;

        foreach ($vk->vkEnumerateInstanceExtensionProperties() as $extension) {
            if ($extension->extensionName === self::PORTABILITY_ENUMERATION_EXTENSION) {
                $instanceExtensions[] = self::PORTABILITY_ENUMERATION_EXTENSION;
                $instanceFlags |= VkInstanceCreateFlagBits::VK_INSTANCE_CREATE_ENUMERATE_PORTABILITY_BIT_KHR;
            }
        }

        if ($window !== null) {
            $instanceExtensions[] = 'VK_KHR_get_surface_capabilities2';
            $deviceExtensions[] = 'VK_KHR_swapchain';
        }

        if ($options->isValidationEnabled) {
            $layers[] = 'VK_LAYER_KHRONOS_validation';
            $instanceExtensions[] = 'VK_EXT_debug_utils';
        }

        if ($options->isRayQueryEnabled) {
            $deviceExtensions = [...$deviceExtensions, ...self::RAY_QUERY_EXTENSIONS];
        }

        return new self($layers, $instanceExtensions, $deviceExtensions, $options->isRayQueryEnabled, $instanceFlags);
    }

    /**
     * The extensions that the device is created with.
     *
     * @return list<non-empty-string>
     */
    public function getDeviceExtensions(Vulkan $vk, VkPhysicalDevice $physical): array
    {
        foreach ($vk->vkEnumerateDeviceExtensionProperties($physical) as $extension) {
            if ($extension->extensionName === self::PORTABILITY_SUBSET_EXTENSION) {
                return [...$this->deviceExtensions, self::PORTABILITY_SUBSET_EXTENSION];
            }
        }

        return $this->deviceExtensions;
    }

    /**
     * The features that the device is created with.
     *
     * @return non-empty-list<VkDeviceCreateInfoExtension>
     */
    public function createFeatureChain(): array
    {
        $chain = [new VkPhysicalDeviceVulkan13Features(
            bufferDeviceAddress: $this->isRayQueryRequired,
            shaderDemoteToHelperInvocation: true,
            synchronization2: true,
            dynamicRendering: true,
        )];

        if ($this->isRayQueryRequired) {
            $chain[] = new VkPhysicalDeviceAccelerationStructureFeaturesKHR(accelerationStructure: true);
            $chain[] = new VkPhysicalDeviceRayQueryFeaturesKHR(rayQuery: true);
        }

        return $chain;
    }
}
