<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Build;

/**
 * Decides which files of the project belong to the distributable game folder.
 */
final readonly class FileSelector
{
    /**
     * Directories of the project that are scanned for files to copy.
     *
     * @var list<non-empty-string>
     */
    public const array ROOTS = ['app', 'libs', 'resources'];

    private const array SHADER_SOURCE_EXTENSIONS = ['glsl', 'vert', 'frag', 'comp', 'geom', 'tesc', 'tese', 'rgen'];

    /**
     * Files of `app/` that exist only for the development tooling.
     */
    private const array DEVELOPMENT_ONLY = [
        'app/Command/Build/',
        'app/Command/BuildCommand.php',
        'app/Command/BuildShaders/',
        'app/Command/BuildShadersCommand.php',
        'app/Command/Download/',
        'app/Command/DownloadCommand.php',
        'app/Exception/BuildException.php',
        'app/Exception/DownloadException.php',
        'app/Exception/ShaderCompilationException.php',
    ];

    /**
     * @param string $relativePath path from the project root
     */
    public function isIncluded(string $relativePath): bool
    {
        $relativePath = \ltrim(\str_replace('\\', '/', $relativePath), '/');

        if (\basename($relativePath) === '.gitignore') {
            return false;
        }

        if (\str_starts_with($relativePath, 'app/')) {
            return $this->isApplicationFile($relativePath);
        }

        if (\str_starts_with($relativePath, 'resources/')) {
            return !$this->isShaderSource($relativePath);
        }

        if (\preg_match('~^libs/[^/]+/(.+)$~', $relativePath, $matches) === 1) {
            return $this->isLibraryFile($matches[1]);
        }

        return false;
    }

    private function isApplicationFile(string $relativePath): bool
    {
        foreach (self::DEVELOPMENT_ONLY as $excluded) {
            if ($relativePath === $excluded) {
                return false;
            }

            if (\str_ends_with($excluded, '/') && \str_starts_with($relativePath, $excluded)) {
                return false;
            }
        }

        return true;
    }

    private function isLibraryFile(string $pathInLibrary): bool
    {
        if ($pathInLibrary === 'composer.json') {
            return true;
        }

        if (\str_starts_with($pathInLibrary, 'resources/stubs/')) {
            return false;
        }

        return \str_starts_with($pathInLibrary, 'src/')
            || \str_starts_with($pathInLibrary, 'resources/')
            || \str_starts_with($pathInLibrary, 'bin/');
    }

    private function isShaderSource(string $relativePath): bool
    {
        $extension = \strtolower(\pathinfo($relativePath, \PATHINFO_EXTENSION));

        return \in_array($extension, self::SHADER_SOURCE_EXTENSIONS, true);
    }
}
