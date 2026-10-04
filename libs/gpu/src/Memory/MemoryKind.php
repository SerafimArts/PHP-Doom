<?php

declare(strict_types=1);

namespace Local\GPU\Memory;

/**
 * Where the memory of a buffer or an image lives.
 */
enum MemoryKind
{
    /**
     * Video memory, filled through a staging buffer.
     */
    case DEVICE_LOCAL;

    /**
     * System memory visible to the host and to the device, mapped for the whole life of the resource.
     */
    case HOST_VISIBLE;

    /**
     * Video memory that the host can write directly (resizable BAR) when the GPU has it, or system memory when it has
     * not. Meant for data that PHP rewrites every frame.
     */
    case STREAMING;
}
