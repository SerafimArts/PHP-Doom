<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Atlas;

use Local\WAD\Exception\WadException;
use Local\WAD\Image\Picture;
use Local\WAD\Wad;
use Serafim\Doom\Exception\AtlasException;
use Serafim\Doom\Level\Data\AnimationTable;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\SectorState;
use Serafim\Doom\Render\Hud\GraphicsSet;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * The keys of the pictures are `T:<NAME>` for wall textures and `F:<NAME>` for flats, see
 * {@see self::getTextureKey()}.
 */
final readonly class LevelAtlasFactory
{
    public const string SKY_TEXTURE = 'SKY1';

    public function __construct(
        private AnimationTable $animations,
    ) {}

    /**
     * @return non-empty-string
     */
    public static function getTextureKey(string $name): string
    {
        return 'T:' . \strtoupper($name);
    }

    /**
     * @return non-empty-string
     */
    public static function getFlatKey(string $name): string
    {
        return 'F:' . \strtoupper($name);
    }

    /**
     * @return non-empty-string
     */
    public static function getSpriteKey(string $lump): string
    {
        return 'S:' . \strtoupper($lump);
    }

    /**
     * @return non-empty-string
     */
    public static function getGraphicKey(string $name): string
    {
        return 'G:' . \strtoupper($name);
    }

    /**
     * @throws WadException in case of the WAD has no picture that the level needs
     * @throws AtlasException in case of the pictures do not fit
     */
    public function create(
        Wad $wad,
        Level $level,
        ?SpriteSet $sprites = null,
        ?GraphicsSet $graphics = null,
    ): TextureAtlas {
        $builder = new AtlasBuilder();

        $known = \array_keys($wad->readTextureDefinitions());

        foreach ($this->findTextureNames($level, $known) as $name) {
            $picture = $wad->readTexture($name);

            $builder->add(
                self::getTextureKey($name),
                $picture->width,
                $picture->height,
                $picture->pixels,
                self::getMask($picture),
            );
        }

        foreach ($this->findFlatNames($level) as $name) {
            $flat = $wad->readFlat($name);

            $builder->add(self::getFlatKey($name), $flat->width, $flat->height, $flat->pixels);
        }

        $frames = [];

        if ($sprites !== null) {
            $frames = $sprites->frames;
        }

        foreach ($frames as $views) {
            foreach ($views as $view) {
                $picture = $view->picture;

                $builder->add(
                    self::getSpriteKey($picture->name),
                    $picture->width,
                    $picture->height,
                    $picture->pixels,
                    self::getMask($picture),
                );
            }
        }

        foreach ($graphics?->getAll() ?? [] as $name => $picture) {
            $builder->add(
                self::getGraphicKey($name),
                $picture->width,
                $picture->height,
                $picture->pixels,
                self::getMask($picture),
            );
        }

        return $builder->build();
    }

    /**
     * The textures of the level and the other state of every switch that it has.
     *
     * @param list<string|int> $known the names of the textures that the WAD has
     *
     * @return list<non-empty-string>
     */
    private function findTextureNames(Level $level, array $known): array
    {
        $names = [self::SKY_TEXTURE => true];

        foreach ($level->map->sidedefs as $sidedef) {
            foreach ([$sidedef->upperTexture, $sidedef->lowerTexture, $sidedef->middleTexture] as $name) {
                if ($name === null || $name === '') {
                    continue;
                }

                $names[\strtoupper($name)] = true;
                $alternative = $this->animations->findAlternative($name);

                if ($alternative !== null && \in_array($alternative, $known, true)) {
                    $names[$alternative] = true;
                }
            }
        }

        return \array_keys($names);
    }

    /**
     * @return list<non-empty-string>
     */
    private function findFlatNames(Level $level): array
    {
        $names = [];

        foreach ($level->map->sectors as $sector) {
            foreach ([$sector->floorTexture, $sector->ceilingTexture] as $name) {
                if ($name === '' || $name === SectorState::SKY_FLAT) {
                    continue;
                }

                foreach ($this->animations->findCycle($name) ?? [$name] as $frame) {
                    if ($frame === '') {
                        continue;
                    }

                    $names[\strtoupper($frame)] = true;
                }
            }
        }

        return \array_keys($names);
    }

    /**
     * The transparency mask of a picture, {@see null} when every pixel is opaque.
     */
    private static function getMask(Picture $picture): ?string
    {
        if ($picture->isSolid) {
            return null;
        }

        return $picture->mask;
    }
}
