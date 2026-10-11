<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Hud;

use Local\WAD\Exception\WadException;
use Local\WAD\Image\Picture;
use Local\WAD\Wad;
use Serafim\Doom\Render\Data\GraphicsCatalog;

/**
 * The graphics of the status bar, the numbers, the faces of the player, the letters of the messages and the pieces of
 * the intermission screen.
 */
final readonly class GraphicsSet
{
    /**
     * @param array<string, Picture> $pictures by the name of the lump
     */
    private function __construct(
        private array $pictures,
    ) {}

    /**
     * @throws WadException in case of the WAD misses a graphic
     */
    public static function create(Wad $wad, GraphicsCatalog $catalog): self
    {
        $pictures = [];

        foreach ($catalog->names as $name) {
            $pictures[$name] = $wad->readPicture($name);
        }

        return new self($pictures);
    }

    public function find(string $name): ?Picture
    {
        return $this->pictures[$name] ?? null;
    }

    /**
     * @return array<string, Picture>
     */
    public function getAll(): array
    {
        return $this->pictures;
    }
}
