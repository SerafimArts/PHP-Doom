<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use FFI\CData;
use Local\GPU\Exception\WindowException;
use Local\GPU\Window\Window;
use Serafim\Vulkan\Handle\Handle;
use Serafim\Vulkan\Handle\VkInstance;
use Serafim\Vulkan\Handle\VkSurfaceKHR;
use Serafim\Vulkan\Vulkan;

/**
 * The surface created by GLFW, for the platforms without a native surface of the library (macOS with MoltenVK).
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\GPU
 */
final class GlfwSurface
{
    private function __construct() {}

    /**
     * @throws WindowException in case of GLFW fails to create the surface
     */
    public static function create(Vulkan $vk, VkInstance $instance, Window $window): VkSurfaceKHR
    {
        /** @var CData $surface */
        $surface = $vk->ffi->new('VkSurfaceKHR');

        if (!$window->createVulkanSurface($instance->pointer, $surface)) {
            throw WindowException::becauseWindowNotCreated('GLFW failed to create the Vulkan surface');
        }

        $destroy = $vk->vkGetInstanceProc('vkDestroySurfaceKHR', $instance);
        $instancePointer = $instance->pointer;

        return new VkSurfaceKHR($vk, $surface, $instance, static function (Handle $handle) use (
            $destroy,
            $instancePointer,
        ): void {
            $destroy($instancePointer, $handle->pointer, null);
        });
    }
}
