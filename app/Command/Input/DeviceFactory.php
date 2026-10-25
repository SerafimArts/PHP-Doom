<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Input;

use Local\GPU\Device;
use Local\GPU\DeviceOptions;
use Local\GPU\Exception\DeviceException;
use Local\GPU\Window\Window;

/**
 * Creates the device of a command and decides by the {@see RayMode} whether it has ray queries.
 */
final readonly class DeviceFactory
{
    public function __construct(
        /**
         * Creates a device, {@see Device::create()} by default
         *
         * @var \Closure(?Window, DeviceOptions): Device|null
         */
        private ?\Closure $create = null,
    ) {}

    /**
     * @throws DeviceException in case of no GPU fits, or none has ray queries while the mode requires them
     */
    public function create(?Window $window, DeviceOptions $options, RayMode $mode): Device
    {
        if ($mode === RayMode::OFF) {
            return $this->createDevice($window, $options->withRayQuery(false));
        }

        try {
            return $this->createDevice($window, $options->withRayQuery(true));
        } catch (DeviceException $e) {
            if ($mode === RayMode::REQUIRED) {
                throw $e;
            }
        }

        return $this->createDevice($window, $options->withRayQuery(false));
    }

    private function createDevice(?Window $window, DeviceOptions $options): Device
    {
        if ($this->create !== null) {
            return ($this->create)($window, $options);
        }

        return Device::create($window, $options);
    }
}
