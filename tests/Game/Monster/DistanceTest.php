<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Monster;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Monster\Distance;

#[Group('wad')]
#[CoversClass(Distance::class)]
final class DistanceTest extends MonsterTestCase
{
    public function testApproximateDistanceNeverFallsShortAndOvershootsByLessThanTwelvePercent(): void
    {
        foreach ([[100.0, 0.0], [70.0, 70.0], [30.0, -120.0], [-50.0, -50.0]] as [$dx, $dy]) {
            $true = \hypot($dx, $dy);
            $approximate = Distance::approximate($dx, $dy);

            self::assertGreaterThanOrEqual($true * 0.999, $approximate);
            self::assertLessThanOrEqual($true * 1.12, $approximate);
        }
    }
}
