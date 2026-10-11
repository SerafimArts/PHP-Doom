<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Sprite;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Render\Sprite\SpriteSet;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(SpriteSet::class)]
final class SpriteSetTest extends TestCase
{
    public function testFrameWithEightViewsUsesMirroredPicturesForTheOtherSide(): void
    {
        $set = $this->createSet();
        $frame = $set->findFrame('POSS', 'A');

        self::assertNotNull($frame);

        $views = $set->frames[$frame];

        self::assertCount(SpriteSet::VIEWS, $views);
        self::assertSame('POSSA1', $views[0]->picture->name);
        self::assertFalse($views[0]->isFlipped);
        self::assertSame('POSSA2A8', $views[1]->picture->name);
        self::assertFalse($views[1]->isFlipped);
        self::assertSame('POSSA2A8', $views[7]->picture->name);
        self::assertTrue($views[7]->isFlipped, 'the view 8 is the view 2 mirrored');
        self::assertSame('POSSA5', $views[4]->picture->name);
    }

    public function testFrameThatLooksTheSameFromEverywhereFillsAllTheViews(): void
    {
        $set = $this->createSet();
        $frame = $set->findFrame('BON1', 'C');

        self::assertNotNull($frame);

        foreach ($set->frames[$frame] as $view) {
            self::assertSame('BON1C0', $view->picture->name);
            self::assertFalse($view->isFlipped);
        }
    }

    public function testFramesAreNumberedInTheOrderOfTheRequest(): void
    {
        $set = $this->createSet();

        self::assertSame(0, $set->findFrame('POSS', 'A'));
        self::assertSame(1, $set->findFrame('POSS', 'B'));
        self::assertNull($set->findFrame('POSS', 'V'), 'frames that were not asked for are not loaded');
        self::assertNull($set->findFrame('XXXX', 'A'));
    }

    public function testWeaponsInTheHandsHaveOnePicture(): void
    {
        $set = $this->createSet();

        foreach (['PUNG', 'PISG', 'SHTG'] as $weapon) {
            self::assertNotNull($set->findPicture($weapon, 'A'), $weapon);
        }
    }

    private function createSet(): SpriteSet
    {
        $pathname = \dirname(__DIR__, 3) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        return SpriteSet::create(Wad::open($pathname), $this->getRenderData()->sprites->frames);
    }
}
