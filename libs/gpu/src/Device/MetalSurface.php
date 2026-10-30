<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use FFI\CData;
use Local\GPU\Window\Window;
use Serafim\Vulkan\Handle\VkInstance;
use Serafim\Vulkan\Handle\VkSurfaceKHR;
use Serafim\Vulkan\Struct\VkMetalSurfaceCreateInfoEXT;
use Serafim\Vulkan\Vulkan;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\GPU
 */
final class MetalSurface
{
    private const string OBJC = '/usr/lib/libobjc.A.dylib';

    private const string RUNTIME = 'void* objc_getClass(const char* name); void* sel_registerName(const char* name);';

    private static ?\FFI $runtime = null;

    private static ?\FFI $send = null;

    private static ?\FFI $sendObject = null;

    private static ?\FFI $sendBool = null;

    private static ?\FFI $sendDouble = null;

    private static ?\FFI $receiveDouble = null;

    private function __construct() {}

    public static function create(Vulkan $vk, VkInstance $instance, Window $window): VkSurfaceKHR
    {
        // The arm64 calling convention requires `objc_msgSend` to be called with the exact signature of the method,
        // so every signature has its own declaration
        self::$runtime ??= \FFI::cdef(self::RUNTIME, self::OBJC);
        self::$send ??= \FFI::cdef('void* objc_msgSend(void* self, void* op);', self::OBJC);
        self::$sendObject ??= \FFI::cdef('void objc_msgSend(void* self, void* op, void* value);', self::OBJC);
        self::$sendBool ??= \FFI::cdef('void objc_msgSend(void* self, void* op, bool value);', self::OBJC);
        self::$sendDouble ??= \FFI::cdef('void objc_msgSend(void* self, void* op, double value);', self::OBJC);
        self::$receiveDouble ??= \FFI::cdef('double objc_msgSend(void* self, void* op);', self::OBJC);

        $view = self::$runtime->cast('void*', $window->getCocoaView());

        // The same as GLFW does: the view is backed by a new `CAMetalLayer` that the surface presents to
        /** @var CData $layer */
        // @phpstan-ignore-next-line the functions are declared by the cdefs above
        $layer = self::$send->objc_msgSend(self::$runtime->objc_getClass('CAMetalLayer'), self::selector('layer'));
        // @phpstan-ignore-next-line the function is declared by the cdef above
        $nsWindow = self::$send->objc_msgSend($view, self::selector('window'));
        // @phpstan-ignore-next-line the function is declared by the cdef above
        $scale = self::$receiveDouble->objc_msgSend($nsWindow, self::selector('backingScaleFactor'));

        // @phpstan-ignore-next-line the function is declared by the cdef above
        self::$sendDouble->objc_msgSend($layer, self::selector('setContentsScale:'), $scale);
        // @phpstan-ignore-next-line the function is declared by the cdef above
        self::$sendObject->objc_msgSend($view, self::selector('setLayer:'), $layer);
        // @phpstan-ignore-next-line the function is declared by the cdef above
        self::$sendBool->objc_msgSend($view, self::selector('setWantsLayer:'), true);

        return $vk->vkCreateMetalSurfaceEXT($instance, new VkMetalSurfaceCreateInfoEXT(
            layer: $layer,
        ));
    }

    private static function selector(string $name): CData
    {
        \assert(self::$runtime !== null);

        /** @var CData $selector */
        // @phpstan-ignore-next-line the function is declared by the cdef of the runtime
        $selector = self::$runtime->sel_registerName($name);

        return $selector;
    }
}
