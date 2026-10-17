<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Monster\NoiseAlert;
use Serafim\Doom\Game\Physics\LineTable;

#[Group('wad')]
#[CoversClass(NoiseAlert::class)]
final class NoiseAlertTest extends MonsterTestCase
{
    public function testNoiseOfAShotReachesTheRoomOfTheShooterAndNotTheFarOnes(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $sectors = $world->state->sectors;

        $noise = new NoiseAlert(new LineTable($level), $sectors);
        $start = $level->findSector((int) $world->player->x, (int) $world->player->y);
        $far = $level->findSector(3008, -4416);

        $noise->alert($start);

        self::assertTrue($noise->isReached($start));
        self::assertFalse($noise->isReached($far), 'the closed doors and the distance keep the noise from the far imp');
    }

    public function testClosedDoorLetsNoSoundThrough(): void
    {
        $level = $this->getLevel();
        $world = $this->createWorld($level);
        $sectors = $world->state->sectors;
        $table = new LineTable($level);

        foreach ($level->map->linedefs as $index => $linedef) {
            if ($linedef->special !== 1 || $linedef->leftSidedef === null) {
                continue;
            }

            $door = $level->map->sidedefs[$linedef->leftSidedef]->sector;
            $room = $level->map->sidedefs[$linedef->rightSidedef]->sector;

            $closed = new NoiseAlert($table, $sectors);
            $closed->alert($room);

            self::assertFalse($closed->isReached($door), 'a closed door has no opening');
            self::assertSame($index, $index);

            return;
        }

        self::fail('the level has no door');
    }
}
