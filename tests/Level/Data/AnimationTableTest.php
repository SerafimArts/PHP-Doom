<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Level\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Level\Data\AnimationTable;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(AnimationTable::class)]
final class AnimationTableTest extends TestCase
{
    public function testFlatsOfACycleShowTheNextFrameEveryEightTics(): void
    {
        $table = $this->getGameData()->animations;

        self::assertSame(8, $table->ticsPerFrame);
        self::assertSame(['NUKAGE1', 'NUKAGE2', 'NUKAGE3'], $table->findCycle('NUKAGE2'));
        self::assertSame('NUKAGE1', $table->getFrame('NUKAGE1', 0));
        self::assertSame('NUKAGE2', $table->getFrame('NUKAGE1', 8));
        self::assertSame('NUKAGE1', $table->getFrame('NUKAGE1', 24));
        self::assertSame('NUKAGE1', $table->getFrame('NUKAGE3', 8), 'a cycle wraps around');
    }

    public function testStillFlatHasNoCycleAndStaysTheSame(): void
    {
        $table = $this->getGameData()->animations;

        self::assertNull($table->findCycle('FLOOR4_8'));
        self::assertSame('FLOOR4_8', $table->getFrame('FLOOR4_8', 100));
    }

    public function testCyclesWithTheSameNamePatternDoNotMixUp(): void
    {
        $table = $this->getGameData()->animations;

        self::assertSame(['SLIME01', 'SLIME02', 'SLIME03', 'SLIME04'], $table->findCycle('SLIME03'));
        self::assertSame(['SLIME05', 'SLIME06', 'SLIME07', 'SLIME08'], $table->findCycle('SLIME05'));
    }

    public function testSwitchTexturesTurnIntoTheirOtherState(): void
    {
        $table = $this->getGameData()->animations;

        self::assertSame('SW2COMM', $table->findAlternative('SW1COMM'));
        self::assertSame('SW1EXIT', $table->findAlternative('sw2exit'));
        self::assertNull($table->findAlternative('STARTAN2'));
    }
}
