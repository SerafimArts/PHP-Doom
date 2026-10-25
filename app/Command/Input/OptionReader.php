<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Input;

use Symfony\Component\Console\Input\InputInterface;

final readonly class OptionReader
{
    public function __construct(
        private InputInterface $input,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getString(string $name): string
    {
        $value = $this->input->getOption($name);
        \assert(\is_string($value) && $value !== '');

        return $value;
    }

    public function findFloat(string $name): ?float
    {
        $value = $this->input->getOption($name);

        if (!\is_string($value) || !\is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * An option that is a size in pixels or any other count from 1 to 65535: out of range values are clamped.
     *
     * @return int<1, 65535>
     */
    public function getDimension(string $name): int
    {
        return \min(65535, \max(1, (int) $this->getString($name)));
    }

    public function getBool(string $name): bool
    {
        return $this->input->getOption($name) === true;
    }

    public function getRayMode(): RayMode
    {
        if ($this->getBool('no-rays')) {
            return RayMode::OFF;
        }

        if ($this->getBool('rays')) {
            return RayMode::REQUIRED;
        }

        return RayMode::AUTO;
    }
}
