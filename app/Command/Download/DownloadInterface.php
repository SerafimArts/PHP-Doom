<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Download;

use Serafim\Doom\Exception\DownloadException;

/**
 * A piece of reference material that is placed under `var/`.
 */
interface DownloadInterface
{
    /**
     * Name that is shown to the user, relative to the target directory.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    public function isDownloaded(): bool;

    /**
     * @throws DownloadException in case of the material cannot be downloaded or verified
     */
    public function download(): void;
}
