<?php

declare(strict_types=1);

namespace Local\GLFW;

use FFI\CData;
use FFI\Location\Locator;
use Local\GLFW\Exception\GlfwNotSupportedException;

/**
 * GLFW looks for the Vulkan loader only in the default paths of the dynamic linker, which do not include the directory
 * of Homebrew. The loader that is found by the locator is given to GLFW instead.
 */
final class VulkanLoader
{
    private static ?\FFI $loader = null;

    private function __construct() {}

    /**
     * Has to be called before `glfwInit()`.
     *
     * @throws GlfwNotSupportedException in case of the Vulkan loader is not installed
     */
    public static function attach(\FFI $glfw): void
    {
        $library = Locator::resolve('libvulkan.1.dylib', 'libvulkan.dylib')
            ?? throw GlfwNotSupportedException::becauseLibraryIsNotInstalled('vulkan-loader');

        // The library has to stay loaded while GLFW uses the entry point
        self::$loader ??= \FFI::cdef('void vkGetInstanceProcAddr(void);', $library);

        /** @var CData $entry */
        // @phpstan-ignore-next-line the function is declared by the cdef of the line above
        $entry = self::$loader->vkGetInstanceProcAddr;

        $glfw->glfwInitVulkanLoader($glfw->cast('void*', $entry));
    }
}
