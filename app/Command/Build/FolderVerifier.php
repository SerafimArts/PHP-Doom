<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Build;

use Serafim\Doom\Exception\BuildException;

/**
 * Checks the content of the built folder: what must be there and what must not.
 */
final readonly class FolderVerifier
{
    /**
     * @var list<non-empty-string>
     */
    public const array REQUIRED = [
        'bin/doom',
        'composer.json',
        'vendor/autoload.php',
        'vendor/serafim/vulkan',
        'vendor/symfony/console',
        'libs/glfw/bin/glfw3.dll',
        'libs/glfw/resources/glfw3.h',
        'resources/data/things.json',
        'resources/data/mobj-states.json',
        'resources/shaders/limits.json',
        'resources/shaders/wall.vert.spv',
        'resources/shaders/wall.frag.spv',
        'play.bat',
        'README.txt',
    ];

    /**
     * @var list<non-empty-string>
     */
    public const array FORBIDDEN = [
        'vendor/phpunit',
        'vendor/phpstan',
        'vendor/friendsofphp',
        'vendor/phpat',
        'tests',
        'var/src',
        'var/docs',
        'app/Command/Download',
        'app/Command/BuildShaders',
    ];

    /**
     * @param non-empty-string $pathname
     *
     * @throws BuildException in case of a required file is missing or a development one is present
     */
    public function verify(string $pathname, bool $isWadExpected): void
    {
        $required = self::REQUIRED;

        if ($isWadExpected) {
            $required[] = 'var/wad/doom1.wad';
        }

        foreach ($required as $relative) {
            if (!\file_exists($pathname . '/' . $relative)) {
                throw BuildException::becauseVerificationFailed(\sprintf('"%s" is missing', $relative));
            }
        }

        foreach (self::FORBIDDEN as $relative) {
            if (\file_exists($pathname . '/' . $relative)) {
                throw BuildException::becauseVerificationFailed(\sprintf('"%s" must not be there', $relative));
            }
        }

        $shaders = \glob($pathname . '/resources/shaders/*');

        if ($shaders === false) {
            $shaders = [];
        }

        foreach ($shaders as $shader) {
            if (!\str_ends_with($shader, '.spv') && \basename($shader) !== 'limits.json') {
                throw BuildException::becauseVerificationFailed(\sprintf('"%s" is not a compiled shader', $shader));
            }
        }
    }
}
