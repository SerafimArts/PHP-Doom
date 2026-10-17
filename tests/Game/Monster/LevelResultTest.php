<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\LevelResult;
use Serafim\Doom\Game\PlayerInput;

#[Group('wad')]
#[CoversClass(LevelResult::class)]
final class LevelResultTest extends MonsterTestCase
{
    public function testPercentsOfTheResultAreCountedOutOfTheTotals(): void
    {
        $result = new LevelResult(3, 6, 7, 38, 0, 3, 47, 30);

        self::assertSame(50, $result->getKillPercent());
        self::assertSame(18, $result->getItemPercent());
        self::assertSame(0, $result->getSecretPercent());
    }

    public function testLevelWithoutSomethingToCountIsDone(): void
    {
        $result = new LevelResult(0, 0, 0, 0, 0, 0, 10, 30);

        self::assertSame(100, $result->getKillPercent());
        self::assertSame(100, $result->getItemPercent());
        self::assertSame(100, $result->getSecretPercent());
    }

    public function testResultOfAWorldCountsTheItemsOfTheLevelAndTheTimeInSeconds(): void
    {
        $world = $this->createWorld($this->getLevel());

        for ($tic = 0; $tic < 70; ++$tic) {
            $world->tick(new PlayerInput());
        }

        $result = $world->getResult();

        self::assertSame(2, $result->seconds);
        self::assertSame(30, $result->parSeconds);
        self::assertGreaterThan(0, $result->totalItems);
        self::assertSame($world->totalKills, $result->totalKills);
        self::assertSame(0, $result->items);
    }
}
