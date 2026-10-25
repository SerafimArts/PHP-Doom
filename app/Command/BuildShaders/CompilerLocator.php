<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\BuildShaders;

use Serafim\Doom\Exception\ShaderCompilationException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Finds `glslc` of the Vulkan SDK.
 */
final readonly class CompilerLocator
{
    public function __construct(
        private ExecutableFinder $finder = new ExecutableFinder(),
        /**
         * Directories to look in after `PATH`
         *
         * @var list<non-empty-string>
         */
        private array $directories = [],
    ) {}

    /**
     * @return non-empty-string
     * @throws ShaderCompilationException in case of the compiler is not found
     */
    public function getCompiler(): string
    {
        return $this->findCompiler() ?? throw ShaderCompilationException::becauseCompilerNotFound();
    }

    /**
     * @return non-empty-string|null
     */
    public function findCompiler(): ?string
    {
        $directories = $this->directories;
        $sdk = \getenv('VULKAN_SDK');

        if (\is_string($sdk) && $sdk !== '') {
            \array_unshift($directories, $sdk . '/Bin', $sdk . '/bin');
        }

        $compiler = $this->finder->find('glslc', null, $directories);

        if ($compiler === null || $compiler === '') {
            return null;
        }

        return $compiler;
    }
}
