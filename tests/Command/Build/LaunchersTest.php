<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Command\Build\Launchers;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(Launchers::class)]
final class LaunchersTest extends TestCase
{
    public function testBatchFileRunsTheCommandWithCrlfLineEndings(): void
    {
        $batch = new Launchers()->createBatch('play');

        self::assertStringContainsString("\r\nphp bin/doom play %*\r\n", $batch);
        self::assertStringContainsString('extension_loaded(', $batch);
        self::assertSame(\substr_count($batch, "\n"), \substr_count($batch, "\r\n"));
    }
}
