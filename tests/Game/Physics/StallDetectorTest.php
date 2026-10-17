<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Physics;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Game\Physics\StallDetector;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(StallDetector::class)]
final class StallDetectorTest extends TestCase
{
    public function testStallIsReportedOnceWhenTheSecondIsOver(): void
    {
        $detector = new StallDetector();
        $reports = 0;

        for ($tic = 0; $tic < 200; ++$tic) {
            if ($detector->update(10.0, 20.0, true)) {
                ++$reports;
            }
        }

        self::assertSame(1, $reports);
        self::assertSame(1, $detector->getReportCount());
    }

    public function testStallNeedsTheWholeSecondOfTheSamePosition(): void
    {
        $detector = new StallDetector();

        $detector->update(10.0, 20.0, true);

        for ($tic = 1; $tic < StallDetector::STALL_TICS; ++$tic) {
            self::assertFalse($detector->update(10.0, 20.0, true), 'tic ' . $tic);
        }

        self::assertTrue($detector->update(10.0, 20.0, true));
    }

    public function testMovingPlayerIsNeverStalled(): void
    {
        $detector = new StallDetector();

        for ($tic = 0; $tic < 300; ++$tic) {
            self::assertFalse($detector->update($tic * 1.0, 0.0, true));
        }
    }

    public function testPlayerWhoHoldsNothingIsNotStalled(): void
    {
        $detector = new StallDetector();

        for ($tic = 0; $tic < 300; ++$tic) {
            self::assertFalse($detector->update(5.0, 5.0, false));
        }
    }

    public function testReleasingTheKeyStartsTheCountAgain(): void
    {
        $detector = new StallDetector();

        for ($tic = 0; $tic < StallDetector::STALL_TICS - 1; ++$tic) {
            $detector->update(5.0, 5.0, true);
        }

        $detector->update(5.0, 5.0, false);

        for ($tic = 0; $tic < StallDetector::STALL_TICS - 1; ++$tic) {
            self::assertFalse($detector->update(5.0, 5.0, true));
        }
    }

    public function testSecondStallIsReportedAfterTheFirstEnded(): void
    {
        $detector = new StallDetector();
        $reports = 0;

        for ($round = 0; $round < 3; ++$round) {
            for ($tic = 0; $tic < 100; ++$tic) {
                if ($detector->update($round * 100.0, 0.0, true)) {
                    ++$reports;
                }
            }
        }

        self::assertSame(3, $reports);
    }

    public function testNoMoreThanTheLimitOfReportsIsMade(): void
    {
        $detector = new StallDetector();
        $reports = 0;

        for ($round = 0; $round < 40; ++$round) {
            for ($tic = 0; $tic < StallDetector::STALL_TICS + 2; ++$tic) {
                if ($detector->update($round * 100.0, 0.0, true)) {
                    ++$reports;
                }
            }
        }

        self::assertSame(StallDetector::MAX_REPORTS, $reports);
    }
}
