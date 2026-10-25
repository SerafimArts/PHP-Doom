<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Play;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Command\Play\CollisionLog;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(CollisionLog::class)]
final class CollisionLogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/doom-log-' . \bin2hex(\random_bytes(4));
    }

    protected function tearDown(): void
    {
        @\unlink($this->directory . '/log/collision.log');
        @\rmdir($this->directory . '/log');
        @\rmdir($this->directory);
    }

    public function testReportsAreAppendedAndTheDirectoryIsCreated(): void
    {
        $log = new CollisionLog($this->directory . '/log/collision.log');

        $log->write("first\n");
        $log->write("second\n");

        $text = (string) \file_get_contents($this->directory . '/log/collision.log');

        self::assertStringContainsString("first\n", $text);
        self::assertStringContainsString("second\n", $text);
        self::assertSame(2, \substr_count($text, '['));
    }
}
