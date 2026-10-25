<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Serafim\Doom\Command\Build\OutputGuard;
use Serafim\Doom\Exception\BuildException;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(OutputGuard::class)]
final class OutputGuardTest extends TestCase
{
    /**
     * @return iterable<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    public static function safePathDataProvider(): iterable
    {
        yield 'default output' => ['D:/Projects/Doom/build/windows', 'D:/Projects/Doom/build/windows'];
        yield 'backslashes' => ['D:\\Projects\\Doom\\build\\windows\\', 'D:/Projects/Doom/build/windows'];
        yield 'dot segments' => ['D:/Projects/Doom/build/./a/../windows', 'D:/Projects/Doom/build/windows'];
        yield 'drive letter case' => ['d:/projects/doom/BUILD/x', 'd:/projects/doom/BUILD/x'];
    }

    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function unsafePathDataProvider(): iterable
    {
        yield 'build itself' => ['D:/Projects/Doom/build'];
        yield 'project root' => ['D:/Projects/Doom'];
        yield 'sibling prefix' => ['D:/Projects/Doom/build-other/x'];
        yield 'escape by dots' => ['D:/Projects/Doom/build/../app'];
        yield 'another drive' => ['E:/Projects/Doom/build/x'];
        yield 'parent' => ['D:/Projects'];
    }

    /**
     * @param non-empty-string $output
     * @param non-empty-string $expected
     */
    #[DataProvider('safePathDataProvider')]
    public function testDirectoryInsideBuildIsAccepted(string $output, string $expected): void
    {
        self::assertSame($expected, new OutputGuard('D:\\Projects\\Doom')->getSafePath($output));
    }

    /**
     * @param non-empty-string $output
     */
    #[DataProvider('unsafePathDataProvider')]
    public function testDirectoryOutsideBuildIsRejected(string $output): void
    {
        $this->expectException(BuildException::class);

        new OutputGuard('D:/Projects/Doom')->getSafePath($output);
    }
}
