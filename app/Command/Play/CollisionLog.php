<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Play;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Appends the reports of a player who does not move to a log file.
 */
final readonly class CollisionLog
{
    public function __construct(
        /** @var non-empty-string */
        public string $pathname,
    ) {}

    public function write(string $report): void
    {
        $filesystem = new Filesystem();

        $filesystem->mkdir(\dirname($this->pathname));
        $filesystem->appendToFile(
            $this->pathname,
            \sprintf("[%s]\n%s\n", \date('Y-m-d H:i:s'), $report),
        );
    }
}
