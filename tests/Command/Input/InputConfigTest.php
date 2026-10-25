<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Input;

use Local\GLFW\Constants\Key;
use Local\GLFW\Constants\MouseButton;
use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Command\Input\InputConfig;
use Serafim\Doom\Command\Play\KeyAction;
use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataReader;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(InputConfig::class)]
final class InputConfigTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/doom-input-' . \bin2hex(\random_bytes(4));
        \mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        @\unlink($this->directory . '/input.json');
        @\rmdir($this->directory);
    }

    public function testProjectFileBindsTheKeysOfTheCommands(): void
    {
        $config = InputConfig::load();

        self::assertSame(KeyAction::USE, $config->bindings->findAction(Key::SPACE));
        self::assertSame(KeyAction::TOGGLE_RAYS, $config->bindings->findAction(Key::F2));
        self::assertSame(Key::W, $config->movement->forward);
        self::assertSame(MouseButton::LEFT, $config->movement->fireButton);
        self::assertSame(0.0025, $config->mouseSensitivity);
        self::assertEqualsWithDelta(640 / 65536 * 2 * \M_PI, $config->keyTurn, 1.0E-12);
        self::assertNotEmpty($config->help['play']);
        self::assertNotEmpty($config->help['fly']);
    }

    public function testUnknownKeyNameThrowsDataFileException(): void
    {
        $this->write($this->createJson('{"USE": ["NO_SUCH_KEY"]}'));

        $this->expectException(DataFileException::class);

        InputConfig::load(new DataReader($this->directory));
    }

    public function testUnknownActionNameThrowsDataFileException(): void
    {
        $this->write($this->createJson('{"DANCE": ["SPACE"]}'));

        $this->expectException(DataFileException::class);

        InputConfig::load(new DataReader($this->directory));
    }

    private function createJson(string $keys): string
    {
        return '{"keys": ' . $keys . ', "movement": {"forward": "W", "back": "S", "right": "D", "left": "A", '
            . '"run": "LEFT_SHIFT", "fire": "LEFT_CONTROL", "fireButton": "LEFT", "turnLeft": "LEFT", '
            . '"turnRight": "RIGHT"}, "mouseSensitivity": 0.0025, "keyTurn": 640, "runTurnFactor": 2, '
            . '"help": {"play": "p", "fly": "f"}}';
    }

    private function write(string $json): void
    {
        \file_put_contents($this->directory . '/input.json', $json);
    }
}
