<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Download;

use Serafim\Doom\Exception\DownloadException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpFile implements DownloadInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private Filesystem $filesystem,
        /** @var non-empty-string */
        private string $name,
        /** @var non-empty-string */
        private string $url,
        /** @var non-empty-string */
        private string $pathname,
        /** @var non-empty-string|null */
        private ?string $md5 = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isDownloaded(): bool
    {
        return \is_file($this->pathname);
    }

    public function download(): void
    {
        $this->filesystem->dumpFile($this->pathname, $this->fetch());

        if ($this->md5 === null) {
            return;
        }

        $actual = \md5_file($this->pathname);

        if ($actual !== $this->md5) {
            $this->filesystem->remove($this->pathname);

            throw DownloadException::becauseChecksumMismatch($this->pathname, $this->md5, (string) $actual);
        }
    }

    private function fetch(): string
    {
        try {
            return $this->client->request('GET', $this->url)->getContent();
        } catch (ExceptionInterface $e) {
            throw DownloadException::becauseDownloadFailed($this->url, $e);
        }
    }
}
