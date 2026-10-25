<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Serafim\Doom\Command\Build\FileSelector;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(FileSelector::class)]
final class FileSelectorTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{non-empty-string, bool}>
     */
    public static function pathDataProvider(): iterable
    {
        yield 'game class' => ['app/Game/TicClock.php', true];
        yield 'play command' => ['app/Command/PlayCommand.php', true];
        yield 'play helper' => ['app/Command/Play/KeyboardInput.php', true];
        yield 'download command' => ['app/Command/DownloadCommand.php', false];
        yield 'download helper' => ['app/Command/Download/HttpFile.php', false];
        yield 'build shaders helper' => ['app/Command/BuildShaders/ShaderCompiler.php', false];
        yield 'build command' => ['app/Command/BuildCommand.php', false];
        yield 'build helper' => ['app/Command/Build/FileSelector.php', false];
        yield 'windows separators' => ['app\\Command\\Download\\HttpFile.php', false];
        yield 'game data table' => ['resources/data/things.json', true];
        yield 'compiled shader' => ['resources/shaders/wall.vert.spv', true];
        yield 'raytracing shader' => ['resources/shaders/wall.frag.rt.spv', true];
        yield 'vertex source' => ['resources/shaders/wall.vert', false];
        yield 'fragment source' => ['resources/shaders/wall.frag', false];
        yield 'glsl include' => ['resources/shaders/level.glsl', false];
        yield 'compute source' => ['resources/shaders/light.comp', false];
        yield 'library source' => ['libs/wad/src/Wad.php', true];
        yield 'library manifest' => ['libs/gpu/composer.json', true];
        yield 'library tests' => ['libs/wad/tests/WadTest.php', false];
        yield 'library examples' => ['libs/gpu/examples/demo.php', false];
        yield 'library phpstan config' => ['libs/gpu/phpstan.neon', false];
        yield 'glfw binary' => ['libs/glfw/bin/glfw3.dll', true];
        yield 'glfw header' => ['libs/glfw/resources/glfw3.h', true];
        yield 'glfw phpstan stub' => ['libs/glfw/resources/stubs/FFI.stub', false];
        yield 'gitignore' => ['libs/glfw/bin/.gitignore', false];
        yield 'root tests' => ['tests/TestCase.php', false];
        yield 'vendor' => ['vendor/autoload.php', false];
        yield 'reference sources' => ['var/src/linuxdoom/p_user.c', false];
    }

    /**
     * @param non-empty-string $path
     */
    #[DataProvider('pathDataProvider')]
    public function testSelectionFollowsTheDistributionRules(string $path, bool $expected): void
    {
        self::assertSame($expected, new FileSelector()->isIncluded($path));
    }
}
