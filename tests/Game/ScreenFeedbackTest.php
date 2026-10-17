<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Game\ScreenFeedback;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(ScreenFeedback::class)]
final class ScreenFeedbackTest extends TestCase
{
    public function testMessageStaysForTheMessageTimeAndGoesAway(): void
    {
        $feedback = new ScreenFeedback();
        $feedback->say('Picked up a clip.');

        for ($tic = 1; $tic < ScreenFeedback::MESSAGE_TICS; ++$tic) {
            $feedback->tick();
        }

        self::assertSame('Picked up a clip.', $feedback->message);

        $feedback->tick();

        self::assertNull($feedback->message);
    }

    public function testPaletteIsNormalWhenNothingHappens(): void
    {
        self::assertSame(0, new ScreenFeedback()->getPalette());
    }

    public function testPainReddensThePaletteAndItFadesWithTime(): void
    {
        $feedback = new ScreenFeedback();
        $feedback->hurt(30);

        self::assertSame(4, $feedback->getPalette());

        for ($tic = 0; $tic < 30; ++$tic) {
            $feedback->tick();
        }

        self::assertSame(0, $feedback->getPalette());
    }

    public function testPainIsCappedAndBeatsTheGoldOfAPickup(): void
    {
        $feedback = new ScreenFeedback();
        $feedback->hurt(500);
        $feedback->bonusCount = 6;

        self::assertSame(8, $feedback->getPalette());
    }

    public function testPickupTurnsThePaletteGoldWhenThePlayerIsNotHurt(): void
    {
        $feedback = new ScreenFeedback();
        $feedback->bonusCount = 6;

        self::assertSame(9, $feedback->getPalette());
    }
}
