<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Level;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Level\LevelState;
use Serafim\Doom\Level\LineGeometry;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(LineGeometry::class)]
final class LineGeometryTest extends TestCase
{
    /**
     * A one-sided line of a pillar from (896, -3392) to (928, -3392): the pillar is on its right, the room on the left
     */
    private const int PILLAR_LINE = 9;

    /**
     * A two-sided line from (1184, -3392) to (928, -3392)
     */
    private const int OPEN_LINE = 39;

    public function testPointAboveALineThatRunsEastIsOnItsLeft(): void
    {
        $geometry = new LineGeometry($this->getLevel()->map);

        self::assertTrue($geometry->isOnLeft(self::PILLAR_LINE, 912.0, -3380.0));
        self::assertFalse($geometry->isOnLeft(self::PILLAR_LINE, 912.0, -3400.0));
    }

    public function testSegmentThatCrossesALineTellsWhereItCrosses(): void
    {
        $geometry = new LineGeometry($this->getLevel()->map);

        self::assertEqualsWithDelta(
            0.4,
            $geometry->findCrossing(self::PILLAR_LINE, 912.0, -3400.0, 912.0, -3380.0),
            1.0E-9,
        );
    }

    public function testSegmentThatMissesOrRunsAlongALineDoesNotCrossIt(): void
    {
        $geometry = new LineGeometry($this->getLevel()->map);

        self::assertNull($geometry->findCrossing(self::PILLAR_LINE, 912.0, -3400.0, 912.0, -3395.0));
        self::assertNull($geometry->findCrossing(self::PILLAR_LINE, 900.0, -3392.0, 920.0, -3392.0));
        self::assertNull($geometry->findCrossing(self::PILLAR_LINE, 1000.0, -3400.0, 1000.0, -3380.0));
    }

    public function testOpeningIsMissingForALineWithOneSideAndPositiveForALineBetweenRooms(): void
    {
        $level = $this->getLevel();
        $geometry = new LineGeometry($level->map);
        $sectors = LevelState::createFromMap($level->map)->sectors;

        self::assertNull($geometry->getOpening(self::PILLAR_LINE, $sectors));
        self::assertGreaterThan(0.0, $geometry->getOpening(self::OPEN_LINE, $sectors));
    }
}
