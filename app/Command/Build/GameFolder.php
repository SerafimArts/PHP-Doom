<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Build;

use Serafim\Doom\Exception\BuildException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The distributable game folder: puts the selected files of the project into it and measures it.
 */
final readonly class GameFolder
{
    /**
     * @param non-empty-string $projectRoot
     * @param non-empty-string $pathname
     */
    public function __construct(
        private string $projectRoot,
        private string $pathname,
        private FileSelector $selector,
        private Filesystem $filesystem,
    ) {}

    /**
     * Copies the files that {@see FileSelector} accepts.
     *
     * @return array<non-empty-string, array{files: int, bytes: int}> statistics by the top directory
     * @throws BuildException in case of a source directory is missing
     */
    public function copySelectedFiles(): array
    {
        $statistics = [];

        foreach (FileSelector::ROOTS as $root) {
            $directory = $this->projectRoot . '/' . $root;

            if (!\is_dir($directory)) {
                throw BuildException::becauseSourceIsMissing($directory);
            }

            $files = 0;
            $bytes = 0;

            foreach (new Finder()->files()->ignoreDotFiles(false)->in($directory) as $file) {
                $relative = $root . '/' . \str_replace('\\', '/', $file->getRelativePathname());

                if (!$this->selector->isIncluded($relative)) {
                    continue;
                }

                $this->filesystem->copy($file->getPathname(), $this->pathname . '/' . $relative, true);
                ++$files;
                $bytes += $file->getSize();
            }

            $statistics[$root] = ['files' => $files, 'bytes' => $bytes];
        }

        return $statistics;
    }

    /**
     * @param non-empty-string $relativePath
     */
    public function write(string $relativePath, string $contents): void
    {
        $this->filesystem->dumpFile($this->pathname . '/' . $relativePath, $contents);
    }

    /**
     * @param non-empty-string $relativePath
     *
     * @return array{files: int, bytes: int}
     */
    public function measure(string $relativePath): array
    {
        $directory = $this->pathname . '/' . $relativePath;
        $result = ['files' => 0, 'bytes' => 0];

        if (\is_file($directory)) {
            return ['files' => 1, 'bytes' => (int) \filesize($directory)];
        }

        if (!\is_dir($directory)) {
            return $result;
        }

        foreach (new Finder()->files()->ignoreDotFiles(false)->ignoreVCS(false)->in($directory) as $file) {
            ++$result['files'];
            $result['bytes'] += $file->getSize();
        }

        return $result;
    }
}
