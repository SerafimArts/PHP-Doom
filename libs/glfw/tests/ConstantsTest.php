<?php

declare(strict_types=1);

namespace Local\GLFW\Tests;

use Local\GLFW\Constants\Glfw;
use Local\GLFW\Constants\Key;
use Local\GLFW\Constants\MouseButton;
use Local\GLFW\GLFW3;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Glfw::class)]
#[CoversClass(Key::class)]
#[CoversClass(MouseButton::class)]
#[CoversClass(GLFW3::class)]
final class ConstantsTest extends TestCase
{
    public function testLettersAndDigitsHaveTheCodesOfAscii(): void
    {
        self::assertSame(\ord('W'), Key::W);
        self::assertSame(\ord('1'), Key::NUM_1);
        self::assertSame(32, Key::SPACE);
    }

    public function testBooleanBecomesTheGlfwValue(): void
    {
        self::assertSame(Glfw::TRUE, Glfw::fromBool(true));
        self::assertSame(Glfw::FALSE, Glfw::fromBool(false));
    }

    public function testLeftButtonIsTheFirstOne(): void
    {
        self::assertSame(0, MouseButton::LEFT);
    }

    #[Group('gpu')]
    public function testLibraryLoadsWhenTheBinaryIsThere(): void
    {
        try {
            $glfw = GLFW3::load();
        } catch (\Throwable $e) {
            self::markTestSkipped('GLFW is not available: ' . $e->getMessage());
        }

        self::assertNotNull($glfw);
    }
}
