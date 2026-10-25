<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Input;

use Local\GPU\Device;
use Local\GPU\DeviceOptions;
use Local\GPU\Exception\DeviceException;
use Local\GPU\Window\Window;
use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Command\Input\DeviceFactory;
use Serafim\Doom\Command\Input\RayMode;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(DeviceFactory::class)]
final class DeviceFactoryTest extends TestCase
{
    public function testPlainModeAsksForTheDeviceWithoutRaysOnly(): void
    {
        $calls = [];

        try {
            $this->createFactory($calls, false)->create(null, new DeviceOptions(isRayQueryEnabled: true), RayMode::OFF);
        } catch (\LogicException) {
            // The fake factory has no device to return.
        }

        self::assertSame([false], $calls);
    }

    public function testAutomaticModeFallsBackToThePlainDeviceWhenTheGpuHasNoRays(): void
    {
        $calls = [];

        try {
            $this->createFactory($calls, true)->create(null, new DeviceOptions(), RayMode::AUTO);
        } catch (\LogicException) {
            // The fake factory has no device to return.
        }

        self::assertSame([true, false], $calls);
    }

    public function testRequiredModeFailsWhenTheGpuHasNoRays(): void
    {
        $calls = [];

        try {
            $this->createFactory($calls, true)->create(null, new DeviceOptions(), RayMode::REQUIRED);

            self::fail('The device without rays must not be accepted');
        } catch (DeviceException) {
            self::assertSame([true], $calls);
        }
    }

    public function testAutomaticModeDoesNotHideOtherFailures(): void
    {
        $calls = [];

        $factory = new DeviceFactory(static function (?Window $window, DeviceOptions $options) use (&$calls): Device {
            $calls[] = $options->isRayQueryEnabled;

            throw new \RuntimeException('Vulkan is broken');
        });

        try {
            $factory->create(null, new DeviceOptions(), RayMode::AUTO);

            self::fail('The failure has to come out');
        } catch (\RuntimeException $e) {
            self::assertSame('Vulkan is broken', $e->getMessage());
        }

        self::assertSame([true], $calls);
    }

    /**
     * @param list<bool> $calls the ray option of every device that was asked for
     */
    private function createFactory(array &$calls, bool $hasNoRays): DeviceFactory
    {
        return new DeviceFactory(static function (
            ?Window $window,
            DeviceOptions $options,
        ) use (&$calls, $hasNoRays): Device {
            $calls[] = $options->isRayQueryEnabled;

            if ($options->isRayQueryEnabled && $hasNoRays) {
                throw DeviceException::becauseNoSuitableDevice();
            }

            throw new \LogicException('A device was created');
        });
    }
}
