<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Layout;

use Serafim\Doom\Level\Geometry\WallPart;
use Serafim\Doom\Level\SidedefState;
use Serafim\Doom\Render\Atlas\TextureAtlas;

/**
 * The picture of every wall part: it follows the textures of the sidedefs, which a switch changes.
 */
final class WallPictureTable
{
    /**
     * Numbers of the pictures of the wall textures by their upper case names
     *
     * @var array<string, int<0, max>>
     */
    private readonly array $pictures;

    private ?int $packedVersion = null;

    private string $packed = '';

    /**
     * @param list<WallPart> $walls
     */
    public function __construct(
        private readonly array $walls,
        TextureAtlas $atlas,
    ) {
        $pictures = [];

        foreach ($atlas->getNumbers() as $key => $number) {
            if (\str_starts_with($key, 'T:')) {
                $pictures[\substr($key, 2)] = $number;
            }
        }

        $this->pictures = $pictures;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->walls);
    }

    /**
     * Bytes of the table for the textures that the sidedefs have now, one `uint` per wall part.
     */
    public function pack(SidedefState $sides): string
    {
        if ($this->packedVersion === $sides->getVersion()) {
            return $this->packed;
        }

        $pictures = [];

        foreach ($this->walls as $wall) {
            $texture = $sides->findTexture($wall->sidedef, $wall->type) ?? $wall->texture;
            $pictures[] = $this->pictures[\strtoupper($texture)] ?? 0;
        }

        $this->packedVersion = $sides->getVersion();

        return $this->packed = \pack('V*', ...$pictures);
    }
}
