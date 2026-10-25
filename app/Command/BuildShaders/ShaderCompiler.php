<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\BuildShaders;

use Serafim\Doom\Exception\ShaderCompilationException;
use Symfony\Component\Process\Process;

/**
 * Compiles a GLSL shader to SPIR-V for Vulkan 1.3 and writes the result next to the source (`*.spv`).
 */
final readonly class ShaderCompiler
{
    public function __construct(
        /** @var non-empty-string */
        private string $compiler,
    ) {}

    /**
     * @param non-empty-string $source pathname of `*.vert`, `*.frag` or `*.comp`
     *
     * @return non-empty-string pathname of the compiled shader
     * @throws ShaderCompilationException in case of the shader is invalid
     */
    public function compile(string $source): string
    {
        return $this->run($source, $source . '.spv', []);
    }

    /**
     * Compiles the variant of a shader that casts ray traced shadows. A shader has one when it includes `shadow.glsl`.
     *
     * @param non-empty-string $source pathname of `*.frag`
     *
     * @return non-empty-string|null pathname of the compiled shader, {@see null} for a shader without the variant
     * @throws ShaderCompilationException in case of the shader is invalid
     */
    public function compileWithRays(string $source): ?string
    {
        $code = \file_get_contents($source);

        if ($code === false || !\str_contains($code, 'shadow.glsl')) {
            return null;
        }

        return $this->run($source, $source . '.rt.spv', ['-DRAY_SHADOWS']);
    }

    /**
     * @param non-empty-string $source
     * @param non-empty-string $target
     * @param list<non-empty-string> $options
     *
     * @return non-empty-string
     * @throws ShaderCompilationException in case of the shader is invalid
     */
    private function run(string $source, string $target, array $options): string
    {
        $process = new Process([$this->compiler, '--target-env=vulkan1.3', '-O', ...$options, $source, '-o', $target]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw ShaderCompilationException::becauseShaderIsInvalid($source, $process->getErrorOutput());
        }

        return $target;
    }
}
