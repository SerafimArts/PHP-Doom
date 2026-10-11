<?php

declare(strict_types=1);

namespace Serafim\Doom\Render\Hud;

use Local\WAD\Image\Picture;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Atlas\TextureAtlas;
use Serafim\Doom\Render\Data\IntermissionLayout;

/**
 * Lays out the screen that follows the exit of a level: the name of the level, the kills, the items and the secrets in
 * percent, the time and the par time. The positions are those of `WI_drawStats` on the screen of 320 x 200 points.
 */
final readonly class IntermissionBuilder
{
    public function __construct(
        private GraphicsSet $graphics,
        private TextureAtlas $atlas,
        private IntermissionLayout $layout,
    ) {}

    /**
     * @return list<HudQuad>
     */
    public function build(IntermissionStats $result): array
    {
        $quads = [];
        $layout = $this->layout;
        $center = \intdiv($layout->screenWidth, 2);

        $this->addPatch($quads, $layout->background, 0, 0);

        $title = $this->graphics->find($layout->title);
        $finished = $this->graphics->find($layout->finished);

        if ($title !== null) {
            $this->addPicture($quads, $title, \intdiv($layout->screenWidth - $title->width, 2), $layout->titleY);

            if ($finished !== null) {
                $y = $layout->titleY + \intdiv(5 * $title->height, 4);
                $this->addPicture($quads, $finished, \intdiv($layout->screenWidth - $finished->width, 2), $y);
            }
        }

        $line = \intdiv(3 * $this->getDigitHeight(), 2);
        $right = $layout->screenWidth - $layout->statsX;
        $rows = [
            [$layout->kills, $result->killPercent],
            [$layout->items, $result->itemPercent],
            [$layout->secrets, $result->secretPercent],
        ];

        foreach ($rows as $index => [$picture, $percent]) {
            $y = $layout->statsY + $index * $line;

            $this->addPatch($quads, $picture, $layout->statsX, $y);
            $this->addPercent($quads, $right, $y, $percent);
        }

        $this->addPatch($quads, $layout->time, $layout->timeX, $layout->timeY);
        $this->addTime($quads, $center - $layout->timeX, $layout->timeY, $result->seconds);

        $this->addPatch($quads, $layout->par, $center + $layout->timeX, $layout->timeY);
        $this->addTime($quads, $layout->screenWidth - $layout->timeX, $layout->timeY, $result->parSeconds);

        return $quads;
    }

    /**
     * A number and the percent sign right of it; the number ends where the sign begins (`WI_drawPercent`).
     *
     * @param list<HudQuad> $quads
     */
    private function addPercent(array &$quads, int $x, int $y, int $percent): void
    {
        $this->addPatch($quads, $this->layout->percent, $x, $y);
        $this->addNumber($quads, $x, $y, $percent, 0);
    }

    /**
     * Minutes and seconds, the colon stands before every pair of digits; a long time is a mocking text
     * (`WI_drawTime`).
     *
     * @param list<HudQuad> $quads
     */
    private function addTime(array &$quads, int $x, int $y, int $seconds): void
    {
        if ($seconds > $this->layout->longestTime) {
            $sucks = $this->graphics->find($this->layout->sucks);

            if ($sucks !== null) {
                $this->addPicture($quads, $sucks, $x - $sucks->width, $y);
            }

            return;
        }

        $colon = $this->graphics->find($this->layout->colon);
        $divisor = 1;

        do {
            $x = $this->addNumber(
                $quads,
                $x,
                $y,
                \intdiv($seconds, $divisor) % 60,
                $this->layout->timeDigits,
            ) - ($colon->width ?? 0);
            $divisor *= 60;

            if ($colon !== null && ($divisor === 60 || \intdiv($seconds, $divisor) > 0)) {
                $this->addPicture($quads, $colon, $x, $y);
            }
        } while (\intdiv($seconds, $divisor) > 0);
    }

    /**
     * Digits that end at the right edge. With `$digits` of 0 the number has as many digits as it needs. Returns where
     * the first digit starts (`WI_drawNum`).
     *
     * @param list<HudQuad> $quads
     */
    private function addNumber(array &$quads, int $right, int $y, int $value, int $digits): int
    {
        $text = (string) \max(0, $value);

        if ($digits > 0) {
            $text = \str_pad($text, $digits, '0', \STR_PAD_LEFT);
            $text = \substr($text, -$digits);
        }

        $x = $right;

        foreach (\array_reverse(\str_split($text)) as $digit) {
            $x -= $this->getDigitWidth();
            $this->addPatch($quads, $this->layout->digitPrefix . $digit, $x, $y);
        }

        return $x;
    }

    private function getDigitWidth(): int
    {
        return $this->graphics->find($this->layout->digitPrefix . '0')->width ?? 0;
    }

    private function getDigitHeight(): int
    {
        return $this->graphics->find($this->layout->digitPrefix . '0')->height ?? 0;
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
}
