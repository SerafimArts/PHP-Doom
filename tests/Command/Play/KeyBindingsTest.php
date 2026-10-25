<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Play;

use Local\GLFW\Constants\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Serafim\Doom\Command\Input\InputConfig;
use Serafim\Doom\Command\Play\KeyAction;
use Serafim\Doom\Command\Play\KeyBindings;

#[CoversClass(KeyBindings::class)]
#[CoversClass(KeyAction::class)]
final class KeyBindingsTest extends TestCase
{
    public function testSpaceAndEAreTheUseKeys(): void
    {
        $bindings = InputConfig::load()->bindings;

        self::assertSame(KeyAction::USE, $bindings->findAction(Key::SPACE));
        self::assertSame(KeyAction::USE, $bindings->findAction(Key::E));
    }

    public function testDigitsTakeTheWeaponsOfTheSameNumber(): void
    {
        $bindings = InputConfig::load()->bindings;

        self::assertSame(1, $bindings->findAction(Key::NUM_1)?->getWeaponSlot());
        self::assertSame(2, $bindings->findAction(Key::NUM_2)?->getWeaponSlot());
        self::assertSame(3, $bindings->findAction(Key::NUM_3)?->getWeaponSlot());
    }

    public function testKeysThatDoNothingHaveNoAction(): void
    {
        self::assertNull(InputConfig::load()->bindings->findAction(Key::W));
    }

    public function testActionsThatAreNotWeaponsHaveNoWeaponSlot(): void
    {
        self::assertNull(KeyAction::USE->getWeaponSlot());
        self::assertNull(KeyAction::LEAVE->getWeaponSlot());
    }
}
