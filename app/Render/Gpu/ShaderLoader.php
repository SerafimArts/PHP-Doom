<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Gpu;

use Local\GPU\Exception\ResourceException;

/**
 * Reads compiled shaders (`*.spv`) from a directory.
 */
final readonly class ShaderLoader
{
    public function __construct(
        private string $directory,
    ) {}

    /**
     * @param non-empty-string $name file name in the directory, `wall.vert.spv`
     *
     * @return non-empty-string
     * @throws ResourceException in case of the shader is not compiled
     */
    public function load(string $name): string
    {
        $pathname = $this->directory . '/' . $name;
        $code = \file_get_contents($pathname);

        if ($code === false || $code === '') {
            throw ResourceException::becauseFileNotReadable($pathname);
        }

        return $code;
    }
}
