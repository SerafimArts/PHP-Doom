<?php

declare(strict_types=1);

namespace Serafim\Doom\Game;

/**
 * What the player did in a level, the numbers that the intermission screen shows after the exit (`wbstartstruct_t`).
 */
final readonly class LevelResult
{
    public function __construct(
        /** @var int<0, max> */
        public int $kills,
        /** @var int<0, max> */
        public int $totalKills,
        /** @var int<0, max> */
        public int $items,
        /** @var int<0, max> */
        public int $totalItems,
        /** @var int<0, max> */
        public int $secrets,
        /** @var int<0, max> */
        public int $totalSecrets,
        /**
         * Time spent in the level in seconds
         *
         * @var int<0, max>
         */
        public int $seconds,
        /**
         * Par time in seconds
         *
         * @var int<0, max>
         */
        public int $parSeconds,
    ) {}

    /**
     * @return int<0, max>
     */
    public function getKillPercent(): int
    {
        return self::getPercent($this->kills, $this->totalKills);
    }

    /**
     * @return int<0, max>
     */
    public function getItemPercent(): int
    {
        return self::getPercent($this->items, $this->totalItems);
    }

    /**
     * @return int<0, max>
     */
    public function getSecretPercent(): int
    {
        return self::getPercent($this->secrets, $this->totalSecrets);
    }

    /**
     * A level without any of the things counts as done.
     *
     * @param int<0, max> $count
     * @param int<0, max> $total
     *
     * @return int<0, max>
     */
    private static function getPercent(int $count, int $total): int
    {
        if ($total === 0) {
            return 100;
        }

        return \max(0, \intdiv($count * 100, $total));
    }
}
