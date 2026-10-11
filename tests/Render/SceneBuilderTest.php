<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Render\Hud\HudBuilder;
use Serafim\Doom\Render\Hud\HudState;
use Serafim\Doom\Render\Hud\IntermissionStats;
use Serafim\Doom\Render\Layout\SpriteInstance;
use Serafim\Doom\Render\Scene;
use Serafim\Doom\Render\Scene\SceneState;
use Serafim\Doom\Render\Scene\SceneThing;
use Serafim\Doom\Render\SceneBuilder;
use Serafim\Doom\Render\SceneBuilderFactory;
use Serafim\Doom\Tests\Render\Support\HudFixture;
use Serafim\Doom\Tests\TestCase;

/**
 * The scene of a frame is built from plain objects, the game is not involved.
 */
#[Group('wad')]
#[CoversClass(SceneBuilder::class)]
final class SceneBuilderTest extends TestCase
{
    public function testEveryThingWithAKnownFrameBecomesASpriteInstance(): void
    {
        $scene = $this->createBuilder()->build($this->createState([
            $this->createThing('POSS', 'A'),
            $this->createThing('POSS', 'B'),
            $this->createThing('XXXX', 'A'),
        ]), 0.0, false);

        self::assertSame(2, $scene->spriteCount);
        self::assertSame(2 * SpriteInstance::SIZE, \strlen($scene->sprites));
    }

    public function testSceneHoldsNoMoreSpritesThanTheBufferHolds(): void
    {
        $things = \array_fill(0, 600, $this->createThing('POSS', 'A'));

        $scene = $this->createBuilder()->build($this->createState($things), 0.0, false);

        self::assertSame(Scene::MAX_SPRITES, $scene->spriteCount);
    }

    public function testHudQuadsAreLeftOutWhenTheHudIsOff(): void
    {
        $builder = $this->createBuilder();
        $state = $this->createState([]);

        self::assertSame(0, $builder->build($state, 0.0, false)->quadCount);
        self::assertGreaterThan(0, $builder->build($state, 0.0, true)->quadCount);
    }

    public function testIntermissionHasNoSprites(): void
    {
        $scene = $this->createBuilder()->buildIntermission(new IntermissionStats(100, 50, 0, 75, 30));

        self::assertSame(0, $scene->spriteCount);
    }

    private function createThing(string $sprite, string $frame): SceneThing
    {
        return new SceneThing(3004, 10.0, 20.0, 0.0, 0.0, 20.0, 56.0, $sprite, $frame, false, 160, true, false);
    }

    /**
     * @param list<SceneThing> $things
     */
    private function createState(array $things): SceneState
    {
        return new SceneState(
            $things,
            0.0,
            0.0,
            41.0,
            0.0,
            0.0,
            0.0,
            0,
            false,
            new HudState(100, 0, null, 50, 200, 0, 50, [1 => true], null, 0, false, 0, 160, 0),
        );
    }

    private function createBuilder(): SceneBuilder
    {
        $fixture = HudFixture::create($this->getRenderData());

        if ($fixture === null) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        return new SceneBuilderFactory($fixture->data, $fixture->graphics, $fixture->atlas, $fixture->sprites)
            ->create();
    }
}
