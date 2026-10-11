<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Raytracing;

use Serafim\Doom\Level\Level;

/**
 * The sectors whose floor or ceiling can move: the doors that the player opens and the sectors that tagged lines act
 * on.
 */
final readonly class DynamicSectors
{
    /**
     * Specials of the lines that open the sector behind them: the manual doors (`DR`) and the switch or repeatable
     * doors that are not tagged
     */
    private const array DOOR_SPECIALS = [1, 26, 27, 28, 31, 32, 33, 34, 117, 118];

    /**
     * @return array<int, true>
     */
    public static function find(Level $level): array
    {
        $sectors = [];

        foreach ($level->map->linedefs as $line) {
            if ($line->special === 0) {
                continue;
            }

            if ($line->leftSidedef !== null && \in_array($line->special, self::DOOR_SPECIALS, true)) {
                $sectors[$level->map->sidedefs[$line->leftSidedef]->sector] = true;
            }

            foreach ($level->sectorsByTag[$line->tag] ?? [] as $sector) {
                if ($line->tag !== 0) {
                    $sectors[$sector] = true;
                }
            }
        }

        return $sectors;
    }
}
