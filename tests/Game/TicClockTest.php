<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Game\TicClock;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(TicClock::class)]
final class TicClockTest extends TestCase
{
    public function testFirstFrameRunsNoTics(): void
    {
        self::assertSame(0, new TicClock()->advance(100.0));
    }

    public function testGameRuns35TicsPerSecond(): void
    {
        $clock = new TicClock();
        $clock->advance(0.0);

        $tics = 0;

        for ($frame = 1; $frame <= 600; ++$frame) {
            $tics += $clock->advance($frame / 60);
        }

        self::assertEqualsWithDelta(350, $tics, 1);
    }

    public function testAlphaTellsHowFarTheFrameIsBetweenTics(): void
    {
        $clock = new TicClock();
        $clock->advance(0.0);

        self::assertSame(1, $clock->advance(1.5 / 35));
        self::assertEqualsWithDelta(0.5, $clock->getAlpha(), 1.0E-9);
    }

    public function testSlowFrameDoesNotRunMoreThanTheMaximumOfTics(): void
    {
        $clock = new TicClock();
        $clock->advance(0.0);

        self::assertSame(TicClock::MAX_TICS_PER_FRAME, $clock->advance(10.0));
        self::assertSame(0, $clock->advance(10.0));
        self::assertLessThanOrEqual(1.0, $clock->getAlpha());
    }
}
