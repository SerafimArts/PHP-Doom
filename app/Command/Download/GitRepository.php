<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Download;

use Serafim\Doom\Exception\DownloadException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

final readonly class GitRepository implements DownloadInterface
{
    public function __construct(
        /** @var non-empty-string */
        private string $name,
        /** @var non-empty-string */
        private string $url,
        /** @var non-empty-string */
        private string $directory,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isDownloaded(): bool
    {
        return \is_dir($this->directory . '/.git');
    }

    public function download(): void
    {
        $process = new Process(['git', 'clone', '--depth', '1', $this->url, $this->directory]);
        $process->setTimeout(600);

        try {
            $process->mustRun();
        } catch (ExceptionInterface $e) {
            throw DownloadException::becauseCloneFailed($this->url, $e);
        }
    }
}
