<?php

declare(strict_types=1);

namespace Local\GPU;

use FFI\CData;
use Local\GPU\Device\DebugMessenger;
use Local\GPU\Device\MetalPlatformDriver;
use Local\GPU\Device\MetalSurface;
use Local\GPU\Device\PhysicalDeviceSelector;
use Local\GPU\Device\Requirements;
use Local\GPU\Device\Win32Surface;
use Local\GPU\Exception\DeviceException;
use Local\GPU\Memory\MemoryAllocator;
use Local\GPU\Memory\MemoryKind;
use Local\GPU\Resource\Buffer;
use Local\GPU\Resource\Image;
use Local\GPU\Resource\ImageInfo;
use Local\GPU\Window\Window;
use Serafim\Vulkan\Enum\VkBufferUsageFlagBits;
use Serafim\Vulkan\Enum\VkFilter;
use Serafim\Vulkan\Enum\VkImageTiling;
use Serafim\Vulkan\Enum\VkImageType;
use Serafim\Vulkan\Enum\VkSampleCountFlagBits;
use Serafim\Vulkan\Enum\VkSamplerAddressMode;
use Serafim\Vulkan\Exception\VulkanException;
use Serafim\Vulkan\Handle\VkDebugUtilsMessengerEXT;
use Serafim\Vulkan\Handle\VkDevice;
use Serafim\Vulkan\Handle\VkDeviceMemory;
use Serafim\Vulkan\Handle\VkInstance;
use Serafim\Vulkan\Handle\VkPhysicalDevice;
use Serafim\Vulkan\Handle\VkQueue;
use Serafim\Vulkan\Handle\VkSampler;
use Serafim\Vulkan\Handle\VkSurfaceKHR;
use Serafim\Vulkan\Loader\Platform\PlatformDetector;
use Serafim\Vulkan\Loader\VulkanLoader;
use Serafim\Vulkan\Struct\VkApplicationInfo;
use Serafim\Vulkan\Struct\VkBufferCreateInfo;
use Serafim\Vulkan\Struct\VkBufferDeviceAddressInfo;
use Serafim\Vulkan\Struct\VkComponentMapping;
use Serafim\Vulkan\Struct\VkDeviceCreateInfo;
use Serafim\Vulkan\Struct\VkDeviceQueueCreateInfo;
use Serafim\Vulkan\Struct\VkExtent3D;
use Serafim\Vulkan\Struct\VkImageCreateInfo;
use Serafim\Vulkan\Struct\VkImageSubresourceRange;
use Serafim\Vulkan\Struct\VkImageViewCreateInfo;
use Serafim\Vulkan\Struct\VkInstanceCreateInfo;
use Serafim\Vulkan\Struct\VkMemoryRequirements;
use Serafim\Vulkan\Struct\VkPhysicalDeviceMemoryProperties;
use Serafim\Vulkan\Struct\VkPhysicalDeviceProperties;
use Serafim\Vulkan\Struct\VkSamplerCreateInfo;
use Serafim\Vulkan\Vulkan;

use function Serafim\Vulkan\vk_make_api_version;

/**
 * Vulkan 1.3 instance, device and queue with dynamic rendering and synchronization2 enabled. The device either
 * presents to a window or works without one (offscreen rendering, tests).
 */
final class Device
{
    private function __construct(
        public readonly Vulkan $vk,
        public readonly VkInstance $instance,
        public readonly VkPhysicalDevice $physicalDevice,
        public readonly VkDevice $device,
        public readonly VkQueue $queue,
        /** @var int<0, 4294967295> */
        public readonly int $queueFamily,
        public readonly VkPhysicalDeviceProperties $properties,
        public readonly VkPhysicalDeviceMemoryProperties $memoryProperties,
        private readonly MemoryAllocator $allocator,
        public readonly bool $isRayQueryEnabled,
        public readonly ?VkSurfaceKHR $surface,
        public readonly ?VkDebugUtilsMessengerEXT $messenger,
        /**
         * Declared last: the window is released after the Vulkan objects that depend on it
         */
        public readonly ?Window $window,
    ) {}

    /**
     * @throws DeviceException in case of no suitable GPU is found
     * @throws VulkanException in case of Vulkan fails
     */
    public static function create(?Window $window = null, DeviceOptions $options = new DeviceOptions()): self
    {
        $vk = Vulkan::load(VulkanLoader::createDefault()->withPlatformDetector(
            PlatformDetector::createDefault()->withDriver(new MetalPlatformDriver()),
        ));
        $requirements = Requirements::createFromOptions($window, $options);

        $instance = $vk->vkCreateInstance(new VkInstanceCreateInfo(
            flags: $requirements->instanceFlags,
            applicationInfo: new VkApplicationInfo(
                applicationName: $options->applicationName,
                apiVersion: vk_make_api_version(0, 1, 3, 0),
            ),
            enabledLayerNames: $requirements->layers,
            enabledExtensionNames: $requirements->instanceExtensions,
        ));

        $messenger = null;

        if ($options->isValidationEnabled) {
            $messenger = DebugMessenger::create($vk, $instance, $options);
        }

        $surface = null;

        if ($window !== null) {
            $surface = match (\PHP_OS_FAMILY) {
                'Darwin' => MetalSurface::create($vk, $instance, $window),
                default => Win32Surface::create($vk, $instance, $window),
            };
        }

        $selected = new PhysicalDeviceSelector($vk)->select($instance, $surface, $options->isRayQueryEnabled)
            ?? throw DeviceException::becauseNoSuitableDevice();

        $device = $vk->vkCreateDevice($selected->physical, new VkDeviceCreateInfo(
            chain: $requirements->createFeatureChain(),
            queueCreateInfos: [
                new VkDeviceQueueCreateInfo(queueFamilyIndex: $selected->queueFamily, queuePriorities: [1.0]),
            ],
            enabledExtensionNames: [
                ...$requirements->deviceExtensions,
                ...self::getPortabilityExtensions($vk, $selected->physical),
            ],
        ));

        $memoryProperties = $vk->vkGetPhysicalDeviceMemoryProperties2($selected->physical)->memoryProperties;

        return new self(
            vk: $vk,
            instance: $instance,
            physicalDevice: $selected->physical,
            device: $device,
            queue: $vk->vkGetDeviceQueue($device, $selected->queueFamily, 0),
            queueFamily: $selected->queueFamily,
            properties: $vk->vkGetPhysicalDeviceProperties2($selected->physical)->properties,
            memoryProperties: $memoryProperties,
            allocator: new MemoryAllocator($vk, $device, $memoryProperties),
            isRayQueryEnabled: $options->isRayQueryEnabled,
            surface: $surface,
            messenger: $messenger,
            window: $window,
        );
    }

    /**
     * `VK_KHR_portability_subset` has to be enabled when the device (MoltenVK) exposes it.
     *
     * @return list<non-empty-string>
     */
    private static function getPortabilityExtensions(Vulkan $vk, VkPhysicalDevice $physical): array
    {
        foreach ($vk->vkEnumerateDeviceExtensionProperties($physical) as $extension) {
            if ($extension->extensionName === Requirements::PORTABILITY_SUBSET_EXTENSION) {
                return [Requirements::PORTABILITY_SUBSET_EXTENSION];
            }
        }

        return [];
    }

    public function getDeviceName(): string
    {
        return $this->properties->deviceName;
    }

    public function waitIdle(): void
    {
        $this->vk->vkDeviceWaitIdle($this->device);
    }

    /**
     * @param int<1, max> $size
     * @param int<0, 4294967295> $usage usage flags of `VkBufferUsageFlagBits`
     *
     * @throws DeviceException in case of the memory kind is not available
     */
    public function createBuffer(int $size, int $usage, MemoryKind $kind): Buffer
    {
        $buffer = $this->vk->vkCreateBuffer($this->device, new VkBufferCreateInfo(size: $size, usage: $usage));
        $isAddressable = ($usage & VkBufferUsageFlagBits::VK_BUFFER_USAGE_SHADER_DEVICE_ADDRESS_BIT) !== 0;
        $memory = $this->allocate(
            $this->vk->vkGetBufferMemoryRequirements($this->device, $buffer),
            $kind,
            $isAddressable,
        );

        $this->vk->vkBindBufferMemory($this->device, $buffer, $memory, 0);

        $mapped = null;

        if ($kind !== MemoryKind::DEVICE_LOCAL) {
            /** @var CData $mapped */
            $mapped = $this->vk->ffi->cast('char*', $this->vk->vkMapMemory($this->device, $memory, 0, $size));
        }

        return new Buffer($buffer, $memory, $size, $kind, $mapped);
    }

    /**
     * Creates an image and a view over all its layers.
     *
     * @throws DeviceException in case of the memory kind is not available
     */
    public function createImage(ImageInfo $info): Image
    {
        $image = $this->vk->vkCreateImage($this->device, new VkImageCreateInfo(
            extent: new VkExtent3D($info->width, $info->height, 1),
            imageType: VkImageType::VK_IMAGE_TYPE_2D,
            format: $info->format,
            mipLevels: $info->mipLevels,
            arrayLayers: $info->layers,
            samples: VkSampleCountFlagBits::VK_SAMPLE_COUNT_1_BIT,
            tiling: VkImageTiling::VK_IMAGE_TILING_OPTIMAL,
            usage: $info->usage,
        ));

        $memory = $this->allocate($this->vk->vkGetImageMemoryRequirements($this->device, $image), $info->kind);
        $this->vk->vkBindImageMemory($this->device, $image, $memory, 0);

        $view = $this->vk->vkCreateImageView($this->device, new VkImageViewCreateInfo(
            image: $image,
            components: new VkComponentMapping(),
            subresourceRange: new VkImageSubresourceRange($info->aspect, 0, $info->mipLevels, 0, $info->layers),
            viewType: $info->getViewType(),
            format: $info->format,
        ));

        return new Image($image, $view, $memory, $info);
    }

    /**
     * A sampler without filtering: indexed colors and texel fetches need exactly the texel they address.
     */
    public function createNearestSampler(bool $isRepeating = false): VkSampler
    {
        $mode = VkSamplerAddressMode::VK_SAMPLER_ADDRESS_MODE_CLAMP_TO_EDGE;

        if ($isRepeating) {
            $mode = VkSamplerAddressMode::VK_SAMPLER_ADDRESS_MODE_REPEAT;
        }

        // @phpstan-ignore method.deprecated (the sampler is part of the descriptor set model used here)
        return $this->vk->vkCreateSampler($this->device, new VkSamplerCreateInfo(
            magFilter: VkFilter::VK_FILTER_NEAREST,
            minFilter: VkFilter::VK_FILTER_NEAREST,
            addressModeU: $mode,
            addressModeV: $mode,
            addressModeW: $mode,
            maxLod: 1.0,
        ));
    }

    /**
     * Address of a buffer created with `VK_BUFFER_USAGE_SHADER_DEVICE_ADDRESS_BIT`.
     */
    public function getBufferAddress(Buffer $buffer): int
    {
        return $this->vk->vkGetBufferDeviceAddress(
            $this->device,
            new VkBufferDeviceAddressInfo(buffer: $buffer->handle),
        );
    }

    /**
     * @throws DeviceException in case of no memory type of the kind fits the requirements
     */
    public function allocate(
        VkMemoryRequirements $requirements,
        MemoryKind $kind,
        bool $isAddressable = false,
    ): VkDeviceMemory {
        return $this->allocator->allocate($requirements, $kind, $isAddressable);
    }
}
