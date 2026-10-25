<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\BuildShaders;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Command\BuildShaders\LimitsGenerator;
use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(LimitsGenerator::class)]
final class LimitsGeneratorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/doom-limits-' . \bin2hex(\random_bytes(4));
        \mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        @\unlink($this->directory . '/limits.json');
        @\unlink($this->directory . '/limits.glsl');
        @\rmdir($this->directory);
    }

    public function testNamesBecomeUpperSnakeCaseConstantsWithTheTypeOfTheValue(): void
    {
        \file_put_contents(
            $this->directory . '/limits.json',
            '{"maxLights": 32, "colormapLastRow": 31, "ticsPerSecond": 35.0, "ratio": 0.5}',
        );

        $glsl = new LimitsGenerator()->render($this->directory . '/limits.json');

        self::assertStringContainsString("const int MAX_LIGHTS = 32;\n", $glsl);
        self::assertStringContainsString("const int COLORMAP_LAST_ROW = 31;\n", $glsl);
        self::assertStringContainsString("const float TICS_PER_SECOND = 35.0;\n", $glsl);
        self::assertStringContainsString("const float RATIO = 0.5;\n", $glsl);
    }

    public function testGeneratingWritesTheFileNextToTheSource(): void
    {
        \file_put_contents($this->directory . '/limits.json', '{"maxLights": 8}');

        $target = new LimitsGenerator()->generate($this->directory);

        self::assertSame($this->directory . '/limits.glsl', $target);
        self::assertStringContainsString('const int MAX_LIGHTS = 8;', (string) \file_get_contents((string) $target));
    }

    public function testDirectoryWithoutLimitsGetsNoFile(): void
    {
        self::assertNull(new LimitsGenerator()->generate($this->directory));
    }

    public function testStringValueThrowsDataFileException(): void
    {
        \file_put_contents($this->directory . '/limits.json', '{"name": "text"}');

        $this->expectException(DataFileException::class);

        new LimitsGenerator()->render($this->directory . '/limits.json');
    }

    public function testBrokenJsonThrowsDataFileException(): void
    {
        \file_put_contents($this->directory . '/limits.json', '{"maxLights": ');

        $this->expectException(DataFileException::class);

        new LimitsGenerator()->render($this->directory . '/limits.json');
    }
}
