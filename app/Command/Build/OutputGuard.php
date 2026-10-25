<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Build;

use Serafim\Doom\Exception\BuildException;

/**
 * Protects the file system from the build: only a directory strictly inside `build/` of the project may be
 * created and removed.
 */
final readonly class OutputGuard
{
    /** @var non-empty-string */
    private string $allowed;

    /**
     * @param non-empty-string $projectRoot
     */
    public function __construct(string $projectRoot)
    {
        $this->allowed = \rtrim(self::normalize($projectRoot), '/') . '/build';
    }

    /**
     * @return non-empty-string the normalized absolute path of the output
     * @throws BuildException in case of the output is not inside the build directory of the project
     */
    public function getSafePath(string $output): string
    {
        $normalized = self::normalize($output);

        if (!\str_starts_with(\strtolower($normalized), \strtolower($this->allowed) . '/')) {
            throw BuildException::becauseOutputIsUnsafe($output, $this->allowed);
        }

        return $normalized;
    }

    /**
     * @return non-empty-string
     */
    private static function normalize(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        $prefix = '';

        if (\preg_match('~^([a-zA-Z]:)?/~', $path, $matches) === 1) {
            $prefix = $matches[0];
            $path = \substr($path, \strlen($prefix));
        }

        $parts = [];

        foreach (\explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                \array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        $result = $prefix . \implode('/', $parts);

        if ($result === '') {
            return '.';
        }

        return $result;
    }
}
