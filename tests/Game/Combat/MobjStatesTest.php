<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Combat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Thing\MobjStates;
use Serafim\Doom\Game\Thing\ThingSpawner;

#[Group('wad')]
#[CoversClass(MobjStates::class)]
final class MobjStatesTest extends CombatTestCase
{
    public function testPuffsAreRemovedWhenTheirAnimationEnds(): void
    {
        $data = $this->getGameData();
        $states = new MobjStates($data->mobjStates);
        $puff = new ThingSpawner($states, $data->things)->create($data->things->getByName('PUFF'), 0.0, 0.0, 0.0);

        for ($tic = 0; $tic < 16; ++$tic) {
            self::assertFalse($puff->isRemoved);
            $states->tick($puff);
        }

        self::assertTrue($puff->isRemoved, 'four states of four tics');
    }
}
