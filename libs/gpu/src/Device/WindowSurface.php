<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use Local\GPU\Exception\WindowException;
use Local\GPU\Window\Window;
use Serafim\Vulkan\Handle\Handle;
use Serafim\Vulkan\Handle\VkInstance;
use Serafim\Vulkan\Handle\VkSurfaceKHR;
use Serafim\Vulkan\Vulkan;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\GPU
 */
final class WindowSurface
{
    private function __construct() {}

    /**
     * @throws WindowException in case of GLFW fails to create the surface
     */
    public static function create(Vulkan $vk, VkInstance $instance, Window $window): VkSurfaceKHR
    {
        $pointer = $vk->ffi->cast('VkSurfaceKHR', $window->createSurface($instance->pointer));

        $destroy = $vk->vkGetInstanceProc('vkDestroySurfaceKHR', $instance);
        $instancePointer = $instance->pointer;

        return new VkSurfaceKHR($vk, $pointer, $instance, static function (Handle $handle) use (
            $destroy,
            $instancePointer,
        ): void {
            $destroy($instancePointer, $handle->pointer, null);
        });
    }
}
