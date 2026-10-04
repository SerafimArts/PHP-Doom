<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use FFI\CData;
use Local\GPU\Window\Window;
use Serafim\Vulkan\Handle\VkInstance;
use Serafim\Vulkan\Handle\VkSurfaceKHR;
use Serafim\Vulkan\Struct\VkWin32SurfaceCreateInfoKHR;
use Serafim\Vulkan\Vulkan;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\GPU
 */
final class Win32Surface
{
    private static ?\FFI $kernel32 = null;

    private function __construct() {}

    public static function create(Vulkan $vk, VkInstance $instance, Window $window): VkSurfaceKHR
    {
        self::$kernel32 ??= \FFI::cdef('void* GetModuleHandleA(const char*);', 'kernel32.dll');

        /** @var CData $hinstance */
        // @phpstan-ignore-next-line the function is declared by the cdef of the line above
        $hinstance = self::$kernel32->GetModuleHandleA(null);

        return $vk->vkCreateWin32SurfaceKHR($instance, new VkWin32SurfaceCreateInfoKHR(
            hinstance: $hinstance,
            hwnd: $window->getWin32Handle(),
        ));
    }
}
