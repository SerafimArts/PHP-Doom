<?php

declare(strict_types=1);

namespace Local\GPU\Tests;

use Local\GPU\Device;
use Local\GPU\DeviceOptions;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Serafim\Vulkan\Exception\VulkanException;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    protected array $validationMessages = [];

    /**
     * Creates a device without a window with the validation layer when the machine has it. The test is skipped when
     * there is no Vulkan device at all.
     */
    protected function createDevice(): Device
    {
        $options = new DeviceOptions(
            applicationName: 'tests',
            isValidationEnabled: true,
            onValidationMessage: function (string $message): void {
                $this->validationMessages[] = $message;
            },
        );

        try {
            return Device::create(null, $options);
        } catch (VulkanException) {
            // The validation layer is not installed, try without it.
        }

        try {
            return Device::create(null, new DeviceOptions(applicationName: 'tests'));
        } catch (\Throwable $e) {
            self::markTestSkipped('Vulkan is not available: ' . $e->getMessage());
        }
    }

    protected function assertNoValidationMessages(): void
    {
        self::assertSame([], $this->validationMessages);
    }
}
