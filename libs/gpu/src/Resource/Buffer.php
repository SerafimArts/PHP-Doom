<?php

declare(strict_types=1);

namespace Local\GPU\Resource;

use FFI\CData;
use Local\GPU\Exception\ResourceException;
use Local\GPU\Memory\MemoryKind;
use Serafim\Vulkan\Handle\VkBuffer;
use Serafim\Vulkan\Handle\VkDeviceMemory;

/**
 * A `VkBuffer` together with its memory. A host visible buffer stays mapped for its whole life.
 */
final readonly class Buffer
{
    public function __construct(
        public VkBuffer $handle,
        public VkDeviceMemory $memory,
        /** @var int<1, max> */
        public int $size,
        public MemoryKind $kind,
        /**
         * Pointer to the first byte of the mapped memory, {@see null} for a device local buffer
         */
        private ?CData $mapped = null,
    ) {}

    public function isMapped(): bool
    {
        return $this->mapped !== null;
    }

    /**
     * @throws ResourceException in case of the buffer is not host visible or the data does not fit
     */
    public function write(string $data, int $offset = 0): void
    {
        $mapped = $this->mapped ?? throw ResourceException::becauseBufferIsNotMapped();
        $length = \strlen($data);

        if ($offset < 0 || $offset + $length > $this->size) {
            throw ResourceException::becauseDataDoesNotFit($offset + $length, $this->size);
        }

        if ($length === 0) {
            return;
        }

        // @phpstan-ignore-next-line pointer arithmetic of a native pointer
        \FFI::memcpy($mapped + $offset, $data, $length);
    }

    /**
     * @param int<1, max> $length
     *
     * @throws ResourceException in case of the buffer is not host visible or the range is out of the buffer
     */
    public function read(int $length, int $offset = 0): string
    {
        $mapped = $this->mapped ?? throw ResourceException::becauseBufferIsNotMapped();

        if ($offset < 0 || $offset + $length > $this->size) {
            throw ResourceException::becauseDataDoesNotFit($offset + $length, $this->size);
        }

        // @phpstan-ignore-next-line pointer arithmetic of a native pointer
        return \FFI::string($mapped + $offset, $length);
    }
}
