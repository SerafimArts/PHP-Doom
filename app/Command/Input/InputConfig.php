<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Input;

use Local\GLFW\Constants\Key;
use Local\GLFW\Constants\MouseButton;
use Serafim\Doom\Command\Play\KeyAction;
use Serafim\Doom\Command\Play\KeyBindings;
use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataReader;
use Serafim\Doom\Level\Data\DataRecord;

/**
 * The keys of the commands, the sensitivity of the mouse and the lines of help, from `input.json`.
 */
final readonly class InputConfig
{
    private const string FILE = 'input.json';

    public function __construct(
        public KeyBindings $bindings,
        public MovementKeys $movement,
        /**
         * Radians of turn for one pixel of the mouse
         */
        public float $mouseSensitivity,
        /**
         * Radians of turn in one tic of a held arrow key
         */
        public float $keyTurn,
        /**
         * How much faster the arrow keys turn while the run key is held
         */
        public float $runTurnFactor,
        /**
         * The line of help by the name of the command
         *
         * @var array<non-empty-string, non-empty-string>
         */
        public array $help,
    ) {}

    /**
     * @throws DataFileException in case of the file is missing, a field is invalid or a key has no such name
     */
    public static function load(?DataReader $reader = null): self
    {
        $reader ??= DataReader::createDefault();
        $record = $reader->read(self::FILE);

        $helpRecord = $record->getRecord('help');
        $help = [
            'play' => $helpRecord->getString('play'),
            'fly' => $helpRecord->getString('fly'),
        ];

        return new self(
            self::createBindings($record->getRecord('keys')),
            self::createMovement($record->getRecord('movement')),
            $record->getFloat('mouseSensitivity'),
            $record->getInt('keyTurn') / 65536 * 2 * \M_PI,
            $record->getFloat('runTurnFactor'),
            $help,
        );
    }

    /**
     * @throws DataFileException in case of an action or a key has no such name
     */
    private static function createBindings(DataRecord $record): KeyBindings
    {
        $actions = [];

        foreach ($record->getRecords() as $name => $keys) {
            $action = self::findAction((string) $name)
                ?? throw DataFileException::becauseReferenceIsUnknown(self::FILE, 'keys', (string) $name);

            foreach ($keys->getStrings() as $keyName) {
                $actions[self::getKey($keyName, 'keys.' . $name)] = $action;
            }
        }

        return new KeyBindings($actions);
    }

    /**
     * @throws DataFileException in case of a key has no such name
     */
    private static function createMovement(DataRecord $record): MovementKeys
    {
        $button = $record->getString('fireButton');

        return new MovementKeys(
            self::getKey($record->getString('forward'), 'movement.forward'),
            self::getKey($record->getString('back'), 'movement.back'),
            self::getKey($record->getString('right'), 'movement.right'),
            self::getKey($record->getString('left'), 'movement.left'),
            self::getKey($record->getString('run'), 'movement.run'),
            self::getKey($record->getString('fire'), 'movement.fire'),
            MouseButton::findCode($button)
                ?? throw DataFileException::becauseReferenceIsUnknown(self::FILE, 'movement.fireButton', $button),
            self::getKey($record->getString('turnLeft'), 'movement.turnLeft'),
            self::getKey($record->getString('turnRight'), 'movement.turnRight'),
        );
    }

    private static function findAction(string $name): ?KeyAction
    {
        foreach (KeyAction::cases() as $action) {
            if ($action->name === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @throws DataFileException in case of the name is not a key
     */
    private static function getKey(string $name, string $path): int
    {
        return Key::findCode($name)
            ?? throw DataFileException::becauseReferenceIsUnknown(self::FILE, $path, $name);
    }
}
