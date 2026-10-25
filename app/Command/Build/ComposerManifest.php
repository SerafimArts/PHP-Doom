<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Build;

/**
 * The `composer.json` and the console entry point of the distributable folder: runtime packages and commands only.
 */
final readonly class ComposerManifest
{
    /**
     * Packages that only the development commands (download, build-shaders) use.
     */
    private const array DEVELOPMENT_ONLY_PACKAGES = [
        'symfony/finder',
        'symfony/http-client',
        'symfony/process',
    ];

    /**
     * @param array<array-key, mixed> $manifest decoded `composer.json` of the project
     *
     * @return array<array-key, mixed>
     */
    public function createRuntime(array $manifest): array
    {
        $require = $manifest['require'] ?? [];
        \assert(\is_array($require));

        foreach (self::DEVELOPMENT_ONLY_PACKAGES as $package) {
            unset($require[$package]);
        }

        $config = $manifest['config'] ?? [];
        \assert(\is_array($config));
        $config['optimize-autoloader'] = true;
        $config['classmap-authoritative'] = true;

        $runtime = [
            'name' => $manifest['name'] ?? 'serafim/doom',
            'description' => $manifest['description'] ?? 'PHP Doom',
            'type' => 'project',
            'license' => $manifest['license'] ?? 'MIT',
            'repositories' => $manifest['repositories'] ?? [],
            'require' => $require,
            'autoload' => $manifest['autoload'] ?? [],
            'config' => $config,
            'minimum-stability' => $manifest['minimum-stability'] ?? 'stable',
            'prefer-stable' => true,
        ];

        if (isset($manifest['extra'])) {
            $runtime['extra'] = $manifest['extra'];
        }

        return $runtime;
    }

    /**
     * Source of the console entry point without the registrations of the development commands.
     */
    public function createEntryPoint(string $source): string
    {
        $lines = [];

        foreach (\explode("\n", $source) as $line) {
            if (\preg_match('~\b(BuildCommand|BuildShadersCommand|DownloadCommand)\b~', $line) === 1) {
                continue;
            }

            $lines[] = $line;
        }

        return \implode("\n", $lines);
    }
}
