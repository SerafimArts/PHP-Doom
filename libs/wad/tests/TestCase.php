<?php

declare(strict_types=1);

namespace Local\WAD\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static function getShareWarePathname(): string
    {
        return \dirname(__DIR__, 3) . '/var/wad/doom1.wad';
    }

    protected static function skipWithoutShareware(): void
    {
        if (!\is_file(self::getShareWarePathname())) {
            self::markTestSkipped('var/wad/doom1.wad is not downloaded');
        }
    }
}
