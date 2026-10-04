<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use Serafim\Vulkan\Enum\VkPhysicalDeviceType;
use Serafim\Vulkan\Enum\VkQueueFlagBits;
use Serafim\Vulkan\Handle\VkInstance;
use Serafim\Vulkan\Handle\VkPhysicalDevice;
use Serafim\Vulkan\Handle\VkSurfaceKHR;
use Serafim\Vulkan\Struct\VkPhysicalDeviceAccelerationStructureFeaturesKHR;
use Serafim\Vulkan\Struct\VkPhysicalDeviceRayQueryFeaturesKHR;
use Serafim\Vulkan\Struct\VkPhysicalDeviceVulkan12Features;
use Serafim\Vulkan\Struct\VkPhysicalDeviceVulkan13Features;
use Serafim\Vulkan\Vulkan;

use function Serafim\Vulkan\vk_make_api_version;

/**
 * Picks the GPU: Vulkan 1.3 with the features the renderer needs, a queue that draws (and presents to the surface),
 * a discrete GPU before the others.
 */
final readonly class PhysicalDeviceSelector
{
    public function __construct(
        private Vulkan $vk,
    ) {}

    public function select(VkInstance $instance, ?VkSurfaceKHR $surface, bool $isRayQueryRequired): ?SelectedDevice
    {
        $best = null;
        $bestScore = -1;

        foreach ($this->vk->vkEnumeratePhysicalDevices($instance) as $candidate) {
            $properties = $this->vk->vkGetPhysicalDeviceProperties2($candidate)->properties;

            if ($properties->apiVersion < vk_make_api_version(0, 1, 3, 0) || !$this->hasFeatures($candidate)) {
                continue;
            }

            if ($isRayQueryRequired && !$this->hasRayQuery($candidate)) {
                continue;
            }

            $family = $this->findQueueFamily($candidate, $surface);

            if ($family === null) {
                continue;
            }

            $score = 0;

            if ($properties->deviceType === VkPhysicalDeviceType::VK_PHYSICAL_DEVICE_TYPE_DISCRETE_GPU) {
                $score = 1;
            }

            if ($score > $bestScore) {
                $best = new SelectedDevice($candidate, $family);
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function hasFeatures(VkPhysicalDevice $physical): bool
    {
        $features = $this->vk->vkGetPhysicalDeviceFeatures2($physical, [VkPhysicalDeviceVulkan13Features::class]);
        $features13 = $features->chain->get(VkPhysicalDeviceVulkan13Features::class);

        return $features13->dynamicRendering
            && $features13->synchronization2
            && $features13->shaderDemoteToHelperInvocation;
    }

    private function hasRayQuery(VkPhysicalDevice $physical): bool
    {
        $names = [];

        foreach ($this->vk->vkEnumerateDeviceExtensionProperties($physical) as $extension) {
            $names[$extension->extensionName] = true;
        }

        foreach (Requirements::RAY_QUERY_EXTENSIONS as $required) {
            if (!isset($names[$required])) {
                return false;
            }
        }

        $features = $this->vk->vkGetPhysicalDeviceFeatures2($physical, [
            VkPhysicalDeviceVulkan12Features::class,
            VkPhysicalDeviceAccelerationStructureFeaturesKHR::class,
            VkPhysicalDeviceRayQueryFeaturesKHR::class,
        ]);

        return $features->chain->get(VkPhysicalDeviceVulkan12Features::class)->bufferDeviceAddress
            && $features->chain->get(VkPhysicalDeviceAccelerationStructureFeaturesKHR::class)->accelerationStructure
            && $features->chain->get(VkPhysicalDeviceRayQueryFeaturesKHR::class)->rayQuery;
    }

    /**
     * @return int<0, 4294967295>|null
     */
    private function findQueueFamily(VkPhysicalDevice $physical, ?VkSurfaceKHR $surface): ?int
    {
        foreach ($this->vk->vkGetPhysicalDeviceQueueFamilyProperties2($physical) as $index => $family) {
            \assert($index >= 0 && $index <= 4_294_967_295);

            if (($family->queueFamilyProperties->queueFlags & VkQueueFlagBits::VK_QUEUE_GRAPHICS_BIT) === 0) {
                continue;
            }

            if ($surface !== null && !$this->vk->vkGetPhysicalDeviceSurfaceSupportKHR($physical, $index, $surface)) {
                continue;
            }

            return $index;
        }

        return null;
    }
}
