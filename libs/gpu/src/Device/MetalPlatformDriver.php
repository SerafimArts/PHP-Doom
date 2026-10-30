<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use Serafim\Vulkan\Loader\Environment;
use Serafim\Vulkan\Loader\Platform\Platform;
use Serafim\Vulkan\Loader\Platform\PlatformDriverInterface;

/**
 * Loads the declarations of `VK_EXT_metal_surface` on macOS instead of the deprecated `VK_MVK_macos_surface`.
 *
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Local\GPU
 */
final readonly class MetalPlatformDriver implements PlatformDriverInterface
{
    public function supports(Environment $environment): bool
    {
        return $environment->family === 'Darwin';
    }

    public function resolve(Environment $environment): Platform
    {
        return Platform::METAL;
    }
}
