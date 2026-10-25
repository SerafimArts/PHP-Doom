<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Command\Build\ComposerManifest;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(ComposerManifest::class)]
final class ComposerManifestTest extends TestCase
{
    public function testRuntimeManifestHasNoDevelopmentPackagesAndScripts(): void
    {
        $runtime = new ComposerManifest()->createRuntime([
            'name' => 'serafim/doom',
            'repositories' => [['type' => 'path', 'url' => 'libs/*']],
            'require' => [
                'php' => '^8.4',
                'local/wad' => '^1.0',
                'symfony/console' => '^7.4',
                'symfony/http-client' => '^7.4',
                'symfony/process' => '^7.4',
            ],
            'require-dev' => ['phpunit/phpunit' => '^12.0'],
            'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests']],
            'scripts' => ['test' => 'phpunit'],
            'scripts-aliases' => ['test' => ['t']],
            'config' => ['optimize-autoloader' => false, 'platform-check' => true],
        ]);

        self::assertArrayNotHasKey('require-dev', $runtime);
        self::assertArrayNotHasKey('autoload-dev', $runtime);
        self::assertArrayNotHasKey('scripts', $runtime);
        self::assertArrayNotHasKey('scripts-aliases', $runtime);
        self::assertSame(['php' => '^8.4', 'local/wad' => '^1.0', 'symfony/console' => '^7.4'], $runtime['require']);
        self::assertSame([['type' => 'path', 'url' => 'libs/*']], $runtime['repositories']);
        self::assertSame(
            ['optimize-autoloader' => true, 'platform-check' => true, 'classmap-authoritative' => true],
            $runtime['config'],
        );
    }

    public function testEntryPointDropsTheDevelopmentCommands(): void
    {
        $source = <<<'PHP'
            use Serafim\Doom\Command\BuildCommand;
            use Serafim\Doom\Command\BuildShadersCommand;
            use Serafim\Doom\Command\DownloadCommand;
            use Serafim\Doom\Command\PlayCommand;
            $application->addCommand(new BuildCommand());
            $application->addCommand(new BuildShadersCommand());
            $application->addCommand(new DownloadCommand());
            $application->addCommand(new PlayCommand());
            PHP;

        self::assertSame(
            <<<'PHP'
                use Serafim\Doom\Command\PlayCommand;
                $application->addCommand(new PlayCommand());
                PHP,
            new ComposerManifest()->createEntryPoint($source),
        );
    }
}
