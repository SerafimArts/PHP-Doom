<?php

declare(strict_types=1);

namespace Local\GPU;

final readonly class DeviceOptions
{
    public function __construct(
        /** @var non-empty-string */
        public string $applicationName = 'Application',
        public bool $isValidationEnabled = false,
        /**
         * Enables ray queries in shaders and the acceleration structures they trace against
         */
        public bool $isRayQueryEnabled = false,
        /**
         * Receives the warnings and errors of the validation layer, they go to `STDERR` by default
         *
         * @var (\Closure(string): void)|null
         */
        public ?\Closure $onValidationMessage = null,
    ) {}

    public function withRayQuery(bool $isEnabled): self
    {
        return new self(
            $this->applicationName,
            $this->isValidationEnabled,
            $isEnabled,
            $this->onValidationMessage,
        );
    }
}
