<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Sprite;

use Local\WAD\Exception\WadException;
use Local\WAD\Wad;

/**
 * The frames of sprites that the game shows. A frame has 8 views (the sprite turns around), a frame that looks the same
 * from everywhere has one picture for all of them. The name of a sprite lump is the four letters of the sprite, the
 * letter of the frame and the digit of the view, 0 for all the views. Another letter and digit after them name the view
 * that is the same picture mirrored: `POSSA2A8` is the view 2 of the frame A and the mirrored view 8.
 */
final readonly class SpriteSet
{
    public const int VIEWS = 8;

    /**
     * @param array<string, int<0, max>> $blocks the number of every frame by `NAMEL` (the sprite and the frame letter)
     * @param list<list<SpriteRotation>> $frames the 8 views of every frame by the number of the frame
     */
    private function __construct(
        private array $blocks,
        public array $frames,
    ) {}

    /**
     * @param array<non-empty-string, non-empty-string> $wanted the letters of the frames by the sprite
     *
     * @throws WadException in case of the WAD misses the sprite
     */
    public static function create(Wad $wad, array $wanted): self
    {
        $views = [];

        foreach ($wad->listSprites() as $lump) {
            $sprite = \substr($lump, 0, 4);

            if (!isset($wanted[$sprite])) {
                continue;
            }

            foreach (self::parse($lump) as [$letter, $view, $isFlipped]) {
                if (\str_contains($wanted[$sprite], $letter)) {
                    $views[$sprite . $letter][$view] = new SpriteRotation($wad->readPicture($lump), $isFlipped);
                }
            }
        }

        $blocks = [];
        $frames = [];

        foreach ($wanted as $sprite => $letters) {
            foreach (\str_split($letters) as $letter) {
                $key = $sprite . $letter;
                $found = $views[$key] ?? [];

                if ($found === []) {
                    continue;
                }

                $blocks[$key] = \count($frames);
                $frames[] = self::fillViews($found);
            }
        }

        return new self($blocks, $frames);
    }

    /**
     * @return int<0, max>|null
     */
    public function findFrame(string $sprite, string $letter): ?int
    {
        return $this->blocks[$sprite . $letter] ?? null;
    }

    /**
     * The first view of a frame, the only one for a frame that looks the same from everywhere.
     */
    public function findPicture(string $sprite, string $letter): ?SpriteRotation
    {
        $frame = $this->findFrame($sprite, $letter);

        if ($frame === null) {
            return null;
        }

        return $this->frames[$frame][0];
    }

    /**
     * @return array<string, true> the names of the lumps that the frames use
     */
    public function getLumps(): array
    {
        $lumps = [];

        foreach ($this->frames as $views) {
            foreach ($views as $view) {
                $lumps[$view->picture->name] = true;
            }
        }

        return $lumps;
    }

    /**
     * @return list<array{string, int, bool}> the frame letter, the view (0 for all) and whether mirrored
     */
    private static function parse(string $lump): array
    {
        $parsed = [[$lump[4], (int) $lump[5], false]];

        if (\strlen($lump) === 8) {
            $parsed[] = [$lump[6], (int) $lump[7], true];
        }

        return $parsed;
    }

    /**
     * @param array<int, SpriteRotation> $found the views by their numbers, 0 is a view for all sides
     *
     * @return list<SpriteRotation>
     */
    private static function fillViews(array $found): array
    {
        $views = [];
        $first = \reset($found);
        \assert($first !== false);

        for ($view = 1; $view <= self::VIEWS; ++$view) {
            $views[] = $found[$view] ?? $found[0] ?? $first;
        }

        return $views;
    }
}
