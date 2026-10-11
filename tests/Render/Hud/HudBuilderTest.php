<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Hud;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Render\Hud\HudBuilder;
use Serafim\Doom\Render\Hud\HudState;
use Serafim\Doom\Render\Hud\PspriteView;
use Serafim\Doom\Tests\Render\Support\HudFixture;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(HudBuilder::class)]
final class HudBuilderTest extends TestCase
{
    public function testStatusBarHasTheBackgroundTheNumbersAndTheFace(): void
    {
        $quads = $this->createBuilder()->build($this->createState());

        // The bar, the arms panel, 50 and 100 and 0 with their percents, six arms, the face, 8 ammo numbers.
        self::assertGreaterThan(30, \count($quads));
        self::assertLessThan(256, \count($quads));
    }

    public function testMessageAddsOneQuadForEveryLetterButNotForSpaces(): void
    {
        $builder = $this->createBuilder();

        $without = \count($builder->build($this->createState()));
        $with = \count($builder->build($this->createState(message: 'Picked up a clip.')));

        self::assertSame(14, $with - $without, '14 letters and dots, 3 spaces');
    }

    public function testFistHidesTheAmmoNumberOfTheBar(): void
    {
        $builder = $this->createBuilder();

        self::assertGreaterThan(
            \count($builder->build($this->createState(weaponAmmo: null))),
            \count($builder->build($this->createState(weaponAmmo: 50))),
        );
    }

    public function testWeaponInTheHandsAddsAQuadAndAnUnknownFrameAddsNone(): void
    {
        $builder = $this->createBuilder();
        $bare = \count($builder->build($this->createState()));

        $pistol = new PspriteView('PISG', 'A', false, 1.0, 32.0);
        $unknown = new PspriteView('XXXX', 'A', false, 1.0, 32.0);

        self::assertSame($bare + 1, \count($builder->build($this->createState(weapon: $pistol))));
        self::assertSame($bare, \count($builder->build($this->createState(weapon: $unknown))));
    }

    private function createState(
        ?string $message = null,
        ?int $weaponAmmo = 50,
        ?PspriteView $weapon = null,
    ): HudState {
        return new HudState(
            health: 100,
            armor: 0,
            weaponAmmo: $weaponAmmo,
            bullets: 50,
            bulletsCapacity: 200,
            shells: 0,
            shellsCapacity: 50,
            ownedWeapons: [1 => true, 2 => true, 3 => false],
            message: $message,
            damageCount: 0,
            isDead: false,
            tic: 0,
            light: 160,
            extraLight: 0,
            weapon: $weapon,
        );
    }

    private function createBuilder(): HudBuilder
    {
        $fixture = HudFixture::create($this->getRenderData());

        if ($fixture === null) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        return new HudBuilder(
            $fixture->graphics,
            $fixture->atlas,
            $fixture->sprites,
            $fixture->data->statusBar,
            $fixture->data->face,
            $fixture->data->limits,
        );
    }
}
