<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Build;

/**
 * Windows launchers and the readme of the distributable folder.
 */
final readonly class Launchers
{
    /**
     * @param non-empty-string $command the `bin/doom` command to run, for example `play`
     *
     * @return non-empty-string the batch file with CRLF line endings
     */
    public function createBatch(string $command): string
    {
        $lines = [
            '@echo off',
            'setlocal',
            'cd /d "%~dp0"',
            'where php >nul 2>nul',
            'if errorlevel 1 goto nophp',
            'php -r "exit(PHP_VERSION_ID >= 80400 && extension_loaded(\'ffi\') ? 0 : 1);"',
            'if errorlevel 1 goto noffi',
            'php bin/doom ' . $command . ' %*',
            'exit /b %errorlevel%',
            ':nophp',
            'echo PHP was not found in PATH. Install PHP 8.4 or newer and add it to PATH.',
            'pause',
            'exit /b 1',
            ':noffi',
            'echo PHP 8.4 or newer with the ffi extension is required.',
            'echo Enable it in php.ini: extension=ffi',
            'pause',
            'exit /b 1',
            '',
        ];

        return \implode("\r\n", $lines);
    }

    /**
     * @return non-empty-string
     */
    public function createReadme(): string
    {
        $lines = [
            'DOOM E1M1 on PHP and Vulkan',
            '===========================',
            '',
            'Requirements',
            '  - Windows 10 or newer, a GPU with a Vulkan 1.3 driver',
            '  - PHP 8.4 or newer in PATH with the ffi extension enabled (extension=ffi in php.ini)',
            '',
            'Run',
            '  play.bat            walk through the level, from the start to the exit switch',
            '  fly.bat             free camera through the level',
            '  play.bat --help     all options (window size, --seconds, --no-vsync, --no-rays, --validate)',
            '',
            'Controls (play)',
            '  W A S D   move',
            '  Mouse     turn',
            '  Shift     run',
            '  E, Space  use doors, lifts and switches',
            '  F2, L     ray traced lighting on or off (it is on when the GPU supports it)',
            '  Tab       release or capture the mouse',
            '  Esc       quit',
            '',
            'The shareware doom1.wad lives in var/wad/doom1.wad.',
            '',
        ];

        return \implode("\r\n", $lines);
    }
}
