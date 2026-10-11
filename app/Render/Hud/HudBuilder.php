<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Hud;

use Local\WAD\Image\Picture;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Data\FaceRules;
use Serafim\Doom\Render\Data\Limits;
use Serafim\Doom\Render\Data\NumberLayout;
use Serafim\Doom\Render\Data\StatusBarLayout;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * Lays out the screen of the player: the weapon in the hands, the status bar with its numbers and the face, and the
 * message. The positions are those of the original status bar on the screen of 320 x 200 points.
 */
final readonly class HudBuilder
{
    public function __construct(
        private GraphicsSet $graphics,
        private TextureAtlas $atlas,
        private SpriteSet $sprites,
        private StatusBarLayout $layout,
        private FaceRules $face,
        private Limits $limits,
    ) {}

    /**
     * @return list<HudQuad>
     */
    public function build(HudState $state): array
    {
        $quads = [];
        $light = $state->light + ($state->extraLight << 4);

        if ($state->weapon !== null) {
            $this->addPsprite($quads, $state->weapon, $light);
        }

        if ($state->flash !== null) {
            $this->addPsprite($quads, $state->flash, $light);
        }

        $this->addStatusBar($quads, $state);
        $this->addMessage($quads, $state);

        return $quads;
    }

    /**
     * Draws the weapon or its flash where the state of the weapon puts it. A frame that is full bright ignores the
     * light of the sector.
     *
     * @param list<HudQuad> $quads
     * @param int $light the light level of the sector the player stands in, with the light of the muzzle flash
     */
    private function addPsprite(array &$quads, PspriteView $psprite, int $light): void
    {
        $view = $this->sprites->findPicture($psprite->sprite, $psprite->frame);

        if ($view === null) {
            return;
        }

        $picture = $view->picture;
        $number = $this->atlas->findNumber(LevelAtlasFactory::getSpriteKey($picture->name));

        if ($number === null) {
            return;
        }

        $row = 0;

        if (!$psprite->isFullBright) {
            $row = $this->getWeaponRow($light);
        }

        $quads[] = new HudQuad(
            $psprite->x - $picture->leftOffset,
            $psprite->y - $picture->topOffset,
            $picture->width,
            $picture->height,
            $number,
            $row,
        );
    }

    /**
     * The colormap row that lights the weapon for the light level of the sector.
     *
     * @return int<0, max>
     */
    private function getWeaponRow(int $light): int
    {
        $levels = $this->layout->weaponLightLevels;
        $row = ($levels - \min($levels, $light >> 4)) * $this->layout->weaponLightRowsPerLevel
            - $this->layout->weaponLightRowOffset;

        return \max(0, \min($this->limits->colormapLastRow, $row));
    }

    /**
     * @param list<HudQuad> $quads
     */
    private function addStatusBar(array &$quads, HudState $state): void
    {
        $this->addPatch($quads, 'STBAR', 0, $this->layout->barTop);
        $this->addPatch($quads, $this->layout->armsPicture, $this->layout->armsX, $this->layout->barTop);

        if ($state->weaponAmmo !== null) {
            $this->addNumber($quads, $this->layout->ammo, $state->weaponAmmo);
        }

        $this->addNumber($quads, $this->layout->health, $state->health);
        $this->addNumber($quads, $this->layout->armor, $state->armor);

        $this->addWeaponGrid($quads, $state);
        $this->addPatch($quads, $this->getFace($state), $this->layout->faceX, $this->layout->barTop);
        $this->addAmmoRows($quads, $state);
    }

    /**
     * @param list<HudQuad> $quads
     */
    private function addWeaponGrid(array &$quads, HudState $state): void
    {
        $grid = $this->layout->weapons;

        for ($weapon = $grid->first; $weapon <= $grid->last; ++$weapon) {
            $index = $weapon - $grid->first;
            $prefix = $grid->missingPrefix;

            if ($state->ownedWeapons[$weapon] ?? false) {
                $prefix = $grid->ownedPrefix;
            }

            $this->addPatch(
                $quads,
                $prefix . $weapon,
                $grid->x + $index % $grid->columns * $grid->cellWidth,
                $grid->y + \intdiv($index, $grid->columns) * $grid->cellHeight,
            );
        }
    }

    /**
     * @param list<HudQuad> $quads
     */
    private function addAmmoRows(array &$quads, HudState $state): void
    {
        $table = $this->layout->ammoRows;

        foreach ($table->rows as $index => $row) {
            $current = 0;
            $capacity = $row->capacity;

            if ($row->ammo === HudAmmo::BULLETS) {
                $current = $state->bullets;
                $capacity = $state->bulletsCapacity;
            }

            if ($row->ammo === HudAmmo::SHELLS) {
                $current = $state->shells;
                $capacity = $state->shellsCapacity;
            }

            $y = $table->y + $index * $table->rowHeight;

            $this->addDigits($quads, $table->prefix, $current, $table->currentRight, $y, $table->digitWidth);
            $this->addDigits($quads, $table->prefix, $capacity, $table->capacityRight, $y, $table->digitWidth);
        }
    }

    /**
     * @param list<HudQuad> $quads
     */
    private function addMessage(array &$quads, HudState $state): void
    {
        if ($state->message === null) {
            return;
        }

        $x = 0;

        foreach (\str_split(\strtoupper($state->message)) as $letter) {
            $code = \ord($letter);
            $picture = null;

            if ($code >= 33 && $code <= 95) {
                $picture = $this->graphics->find(\sprintf('STCFN%03d', $code));
            }

            if ($picture === null) {
                $x += $this->layout->spaceWidth;

                continue;
            }

            $this->addPicture($quads, $picture, $x, 0);
            $x += $picture->width;
        }
    }

    /**
     * A number with the percent sign that follows it, if the layout has one.
     *
     * @param list<HudQuad> $quads
     */
    private function addNumber(array &$quads, NumberLayout $layout, int $value): void
    {
        $this->addDigits($quads, $layout->prefix, $value, $layout->right, $layout->y, $layout->digitWidth);

        if ($layout->percent !== null) {
            $this->addPatch($quads, $layout->percent->picture, $layout->percent->x, $layout->percent->y);
        }
    }

    /**
     * Digits aligned to the right edge, without leading zeros.
     *
     * @param list<HudQuad> $quads
     */
    private function addDigits(array &$quads, string $prefix, int $value, int $right, int $y, int $digitWidth): void
    {
        $digits = \str_split((string) \max(0, $value));
        $x = $right - \count($digits) * $digitWidth;

        foreach ($digits as $digit) {
            $this->addPatch($quads, $prefix . $digit, $x, $y);
            $x += $digitWidth;
        }
    }

    /**
     * @param list<HudQuad> $quads
     */
    private function addPatch(array &$quads, string $name, int $x, int $y): void
    {
        $picture = $this->graphics->find($name);

        if ($picture !== null) {
            $this->addPicture($quads, $picture, $x, $y);
        }
    }

    /**
     * @param list<HudQuad> $quads
     */
    private function addPicture(array &$quads, Picture $picture, int $x, int $y): void
    {
        $number = $this->atlas->findNumber(LevelAtlasFactory::getGraphicKey($picture->name));

        if ($number === null) {
            return;
        }

        $quads[] = new HudQuad(
            $x - $picture->leftOffset,
            $y - $picture->topOffset,
            $picture->width,
            $picture->height,
            $number,
        );
    }

    private function getFace(HudState $state): string
    {
        if ($state->isDead) {
            return $this->face->deadPicture;
        }

        $health = \min($this->face->maxHealth, $state->health);
        $pain = \intdiv(($this->face->maxHealth - $health) * $this->face->painLevels, $this->face->painDivisor);

        if ($state->damageCount >= $this->face->ouchDamage) {
            return $this->face->ouchPrefix . $pain;
        }

        $step = \intdiv($state->tic, $this->face->lookTics) % \count($this->face->look);

        return $this->face->lookPrefix . $pain . $this->face->look[$step];
    }
}
