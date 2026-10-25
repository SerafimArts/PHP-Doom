<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Play;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Command\Input\InputConfig;
use Serafim\Doom\Command\Input\KeyState;
use Serafim\Doom\Command\Play\KeyboardInput;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(KeyboardInput::class)]
final class KeyboardInputTest extends TestCase
{
    public function testNothingHeldGivesAnEmptyInput(): void
    {
        $input = $this->createInput([])->read();

        self::assertSame(0, $input->forwardMove);
        self::assertSame(0, $input->sideMove);
        self::assertSame(0.0, $input->turn);
        self::assertFalse($input->isUsing);
        self::assertFalse($input->isFiring);
    }

    public function testForwardAndStrafeKeysWalkWithoutShiftAndRunWithIt(): void
    {
        $config = InputConfig::load();
        $held = [$config->movement->forward, $config->movement->right];

        $walking = $this->createInput($held)->read();
        $running = $this->createInput([...$held, $config->movement->run])->read();

        self::assertSame(PlayerInput::WALK_FORWARD, $walking->forwardMove);
        self::assertSame(PlayerInput::WALK_SIDE, $walking->sideMove);
        self::assertSame(PlayerInput::RUN_FORWARD, $running->forwardMove);
        self::assertSame(PlayerInput::RUN_SIDE, $running->sideMove);
    }

    public function testOppositeKeysCancelEachOther(): void
    {
        $movement = InputConfig::load()->movement;

        $input = $this->createInput([$movement->forward, $movement->back])->read();

        self::assertSame(0, $input->forwardMove);
    }

    public function testFireComesFromTheKeyAndFromTheMouseButton(): void
    {
        $movement = InputConfig::load()->movement;

        self::assertTrue($this->createInput([$movement->fire])->read()->isFiring);
        self::assertTrue($this->createInput([], [$movement->fireButton])->read()->isFiring);
    }

    public function testMouseMotionTurnsOnceAndTheRequestsAreSpentByTheRead(): void
    {
        $keyboard = $this->createInput([]);

        $keyboard->addMouseMotion(100.0);
        $keyboard->requestUse();
        $keyboard->requestWeapon(2);

        $first = $keyboard->read();
        $second = $keyboard->read();

        self::assertEqualsWithDelta(-100.0 * InputConfig::load()->mouseSensitivity, $first->turn, 1.0E-12);
        self::assertTrue($first->isUsing);
        self::assertSame(2, $first->weaponSlot);
        self::assertSame(0.0, $second->turn);
        self::assertFalse($second->isUsing);
        self::assertSame(0, $second->weaponSlot);
    }

    public function testArrowKeysTurnFasterWhileRunning(): void
    {
        $config = InputConfig::load();

        $slow = $this->createInput([$config->movement->turnLeft])->read();
        $fast = $this->createInput([$config->movement->turnLeft, $config->movement->run])->read();

        self::assertEqualsWithDelta($config->keyTurn, $slow->turn, 1.0E-12);
        self::assertEqualsWithDelta($config->keyTurn * $config->runTurnFactor, $fast->turn, 1.0E-12);
    }

    /**
     * @param list<int> $keys
     * @param list<int> $buttons
     */
    private function createInput(array $keys, array $buttons = []): KeyboardInput
    {
        $state = new class ($keys, $buttons) implements KeyState {
            /**
             * @param list<int> $keys
             * @param list<int> $buttons
             */
            public function __construct(
                private readonly array $keys,
                private readonly array $buttons,
            ) {}

            #[\Override]
            public function isKeyDown(int $key): bool
            {
                return \in_array($key, $this->keys, true);
            }

            #[\Override]
            public function isMouseButtonDown(int $button): bool
            {
                return \in_array($button, $this->buttons, true);
            }
        };

        return new KeyboardInput($state, InputConfig::load());
    }
}
