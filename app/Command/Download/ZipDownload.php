<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Download;

use Serafim\Doom\Exception\DownloadException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A zip archive that is downloaded and extracted into a directory, the archive itself is not kept.
 */
final readonly class ZipDownload implements DownloadInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private Filesystem $filesystem,
        /** @var non-empty-string */
        private string $name,
        /** @var non-empty-string */
        private string $url,
        /** @var non-empty-string */
        private string $directory,
        /**
         * A file of the archive whose presence means the archive is already extracted
         *
         * @var non-empty-string
         */
        private string $marker,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isDownloaded(): bool
    {
        return \is_file($this->directory . '/' . $this->marker);
    }

    public function download(): void
    {
        $archive = $this->filesystem->tempnam(\sys_get_temp_dir(), 'doom-', '.zip');

        try {
            $this->filesystem->dumpFile($archive, $this->fetch());
            $this->extract($archive);
        } finally {
            $this->filesystem->remove($archive);
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

    private function extract(string $archive): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($archive) !== true) {
            throw DownloadException::becauseArchiveNotExtracted($archive);
        }

        $this->filesystem->mkdir($this->directory);

        if (!$zip->extractTo($this->directory)) {
            throw DownloadException::becauseArchiveNotExtracted($archive);
        }

        $zip->close();
    }
}
