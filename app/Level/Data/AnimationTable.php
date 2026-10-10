<?php

declare(strict_types=1);

namespace Serafim\Doom\Level\Data;

use Serafim\Doom\Exception\DataFileException;

/**
 * The animated flats (`animdef_t` cycles of `p_spec.c`) and the switch textures (`switchlist_t`) of the game.
 */
final readonly class AnimationTable
{
    private const string FILE = 'animations.json';

    /**
     * @param int<1, max> $ticsPerFrame
     * @param array<string, array{cycle: list<non-empty-string>, position: int<0, max>}> $flats the cycle of every flat
     * @param array<string, non-empty-string> $switches the other texture of every switch texture
     */
    public function __construct(
        public int $ticsPerFrame,
        private array $flats,
        private array $switches,
    ) {}

    /**
     * @throws DataFileException in case of the file is invalid
     */
    public static function createFromReader(DataReader $reader): self
    {
        $record = $reader->read(self::FILE);

        $ticsPerFrame = $record->getInt('ticsPerFrame');

        if ($ticsPerFrame < 1) {
            throw DataFileException::becauseFieldIsInvalid(self::FILE, 'ticsPerFrame', 'a positive integer');
        }

        $flats = [];

        foreach ($record->getRecord('flats')->getRecords() as $cycle) {
            $pattern = $cycle->getString('pattern');
            $frames = [];

            for ($number = $cycle->getInt('first'); $number <= $cycle->getInt('last'); ++$number) {
                $frames[] = \sprintf($pattern, $number);
            }

            foreach ($frames as $position => $frame) {
                $flats[$frame] = ['cycle' => $frames, 'position' => $position];
            }
        }

        $switches = [];

        foreach ($record->getRecord('switches')->getRecords() as $pair) {
            $first = $pair->getString('0');
            $second = $pair->getString('1');

            $switches[$first] = $second;
            $switches[$second] = $first;
        }

        return new self($ticsPerFrame, $flats, $switches);
    }

    /**
     * @return list<non-empty-string>|null the names of the flats of the animation, {@see null} for a still flat
     */
    public function findCycle(string $flat): ?array
    {
        return ($this->flats[$flat] ?? null)['cycle'] ?? null;
    }

    /**
     * The flat that is shown at a tic when the sector started with the given one.
     */
    public function getFrame(string $flat, int $tic): string
    {
        $entry = $this->flats[$flat] ?? null;

        if ($entry === null) {
            return $flat;
        }

        return $entry['cycle'][($entry['position'] + \intdiv($tic, $this->ticsPerFrame)) % \count($entry['cycle'])];
    }

    /**
     * The texture that a switch shows after it was used (and before), {@see null} for any other texture.
     */
    public function findAlternative(string $texture): ?string
    {
        return $this->switches[\strtoupper($texture)] ?? null;
    }
}
