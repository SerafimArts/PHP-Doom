<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Combat;

use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\Mobj;

/**
 * What a shot leaves where it hits: a puff of smoke on a wall, a drop of blood on a thing that bleeds.
 */
final readonly class Impacts
{
    private const float BELOW_ANY_FLOOR = -1.0E9;

    private const string PUFF_MELEE_STATE = 'S_PUFF3';

    private const string BLOOD_SMALL_STATE = 'S_BLOOD2';

    private const string BLOOD_TINY_STATE = 'S_BLOOD3';

    public function __construct(
        private GameContext $context,
    ) {}

    public function puff(ShotHit $hit, bool $isMelee): void
    {
        $definition = $this->context->spawner->catalog->getByName('PUFF');

        $puff = $this->context->spawner->create($definition, $hit->x, $hit->y, $hit->z + $this->getJitter());
        $puff->momentumZ = 1.0;
        $puff->floorZ = self::BELOW_ANY_FLOOR;

        $this->shorten($puff);

        if ($isMelee) {
            $this->context->states->set($puff, $this->context->states->table->get(self::PUFF_MELEE_STATE));
        }

        $this->context->things->add($puff);
    }

    public function blood(ShotHit $hit, int $damage): void
    {
        $definition = $this->context->spawner->catalog->getByName('BLOOD');

        $sector = $this->context->level->findSectorAt($hit->x, $hit->y);

        $blood = $this->context->spawner->create($definition, $hit->x, $hit->y, $hit->z + $this->getJitter());
        $blood->momentumZ = 2.0;
        $blood->floorZ = (float) $this->context->sectors->floorHeights[$sector];

        $this->shorten($blood);

        if ($damage <= 12 && $damage >= 9) {
            $this->context->states->set($blood, $this->context->states->table->get(self::BLOOD_SMALL_STATE));
        } elseif ($damage < 9) {
            $this->context->states->set($blood, $this->context->states->table->get(self::BLOOD_TINY_STATE));
        }

        $this->context->things->add($blood);
    }

    /**
     * A puff or a drop of blood rises or falls a bit from where the shot hit
     */
    private function getJitter(): float
    {
        return ($this->context->random->nextByte() - $this->context->random->nextByte()) / 64;
    }

    /**
     * The first frame of a puff or of blood lasts a bit less than the state says, by chance.
     */
    private function shorten(Mobj $thing): void
    {
        $thing->stateTics = \max(1, $thing->stateTics - ($this->context->random->nextByte() & 3));
    }
}
