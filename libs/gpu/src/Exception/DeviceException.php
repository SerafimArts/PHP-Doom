<?php

declare(strict_types=1);

namespace Local\GPU\Exception;

final class DeviceException extends GpuException
{
    public static function becauseNoSuitableDevice(?\Throwable $prev = null): self
    {
        return new self(
            'No Vulkan device with graphics queue, Vulkan 1.3, dynamic rendering and synchronization2 was found',
            0,
            $prev,
        );
    }

    public static function becauseMemoryTypeNotFound(int $typeBits, ?\Throwable $prev = null): self
    {
        return new self(\sprintf('No suitable memory type for the mask 0x%X', $typeBits), 0, $prev);
    }

    public static function becauseWindowRequired(?\Throwable $prev = null): self
    {
        return new self('The operation needs a window, the device was created without one', 0, $prev);
    }
}
