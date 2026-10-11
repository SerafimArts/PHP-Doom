<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Raytracing;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Game\Mobj;
use Serafim\Doom\Game\World;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Render\Raytracing\CasterBuilder;
use Serafim\Doom\Render\Raytracing\LightBuilder;
use Serafim\Doom\Render\Raytracing\ShadowScene;
use Serafim\Doom\Tests\TestCase;

#[Group('wad')]
#[CoversClass(LightBuilder::class)]
#[CoversClass(CasterBuilder::class)]
final class LightBuilderTest extends TestCase
{
    public function testLampsOfTheLevelAreTheOnlyLightsOfAQuietWorld(): void
    {
        $world = $this->createE1M1World();

        [$data, $count] = $this->createBuilder()->build($this->present($world), 0.0);

        self::assertSame(8, $count);
        self::assertSame($count * ShadowScene::LIGHT_SIZE, \strlen($data));

        for ($index = 0; $index < $count; ++$index) {
            self::assertGreaterThan(0.0, $this->readLight($data, $index)[3]);
        }
    }

    public function testLampsFlickerSlightlyWithTime(): void
    {
        $world = $this->createE1M1World();

        [$first] = $this->createBuilder()->build($this->present($world), 0.0);
        [$later] = $this->createBuilder()->build($this->present($world), 0.9);

        $before = $this->readLight($first, 0);
        $after = $this->readLight($later, 0);

        self::assertNotSame($before[4], $after[4]);
        self::assertEqualsWithDelta($before[4], $after[4], $before[4] * 0.2);
    }

    public function testMuzzleFlashAddsAWarmLightNextToThePlayerThatFadesWithTheFlash(): void
    {
        $world = $this->createE1M1World();

        $world->stats->extraLight = 2;
        [$bright, $count] = $this->createBuilder()->build($this->present($world), 0.0);

        $world->stats->extraLight = 1;
        [$dim] = $this->createBuilder()->build($this->present($world), 0.0);

        self::assertSame(9, $count);

        $first = $this->readLight($bright, 0);
        $second = $this->readLight($dim, 0);

        self::assertGreaterThan($second[4], $first[4]);
        self::assertEqualsWithDelta($world->player->x, $first[0], 40.0);
        self::assertEqualsWithDelta($world->player->y, $first[1], 40.0);
        self::assertGreaterThan($first[6], $first[4], 'the flash is warm: more red than blue');
    }

    public function testFireballInFlightAndItsExplosionAreLightsThatMoveWithThem(): void
    {
        $world = $this->createE1M1World();
        $world->things->add(new Mobj(3999, 100.0, 200.0, 30.0, 0.0, 'BAL1', 'A', true, 6.0, 8.0, false));

        [$flight, $count] = $this->createBuilder()->build($this->present($world), 0.0);

        self::assertSame(9, $count);
        self::assertSame(100.0, $this->readLight($flight, 0)[0]);
        self::assertSame(200.0, $this->readLight($flight, 0)[1]);

        $all = $world->things->all();
        $fireball = \end($all);
        \assert($fireball instanceof Mobj);
        $fireball->frame = 'C';
        $fireball->x = 120.0;

        [$explosion] = $this->createBuilder()->build($this->present($world), 0.0);

        self::assertSame(120.0, $this->readLight($explosion, 0)[0]);
        self::assertGreaterThan($this->readLight($flight, 0)[3], $this->readLight($explosion, 0)[3]);
    }

    public function testBarrelExplosionIsBrighterThanAFireball(): void
    {
        $world = $this->createE1M1World();
        $world->things->add(new Mobj(2035, 100.0, 200.0, 0.0, 0.0, 'BEXP', 'B', true, 10.0, 34.0, false));
        $world->things->add(new Mobj(3999, 100.0, 200.0, 0.0, 0.0, 'BAL1', 'B', true, 6.0, 8.0, false));

        [$data] = $this->createBuilder()->build($this->present($world), 0.0);

        self::assertGreaterThan($this->readLight($data, 1)[4], $this->readLight($data, 0)[4]);
    }

    public function testRemovedThingsGiveNoLight(): void
    {
        $world = $this->createE1M1World();
        $fireball = new Mobj(3999, 100.0, 200.0, 30.0, 0.0, 'BAL1', 'A', true, 6.0, 8.0, false);
        $fireball->isRemoved = true;
        $world->things->add($fireball);

        [, $count] = $this->createBuilder()->build($this->present($world), 0.0);

        self::assertSame(8, $count);
    }

    public function testNumberOfLightsNeverExceedsWhatTheShadersRead(): void
    {
        $world = $this->createE1M1World();

        for ($i = 0; $i < 60; ++$i) {
            $world->things->add(new Mobj(3999, 100.0 + $i, 200.0, 30.0, 0.0, 'BAL1', 'A', true, 6.0, 8.0, false));
        }

        [$data, $count] = $this->createBuilder()->build($this->present($world), 0.0);

        $maxLights = $this->getRenderData()->limits->maxLights;

        self::assertSame($maxLights, $count);
        self::assertSame($maxLights * ShadowScene::LIGHT_SIZE, \strlen($data));
    }

    public function testOnlyStandingSolidThingsPutShadowsAndLampsDoNot(): void
    {
        $world = $this->createE1M1World();
        $barrel = new Mobj(2035, 100.0, 200.0, 0.0, 0.0, 'BAR1', 'A', false, 10.0, 34.0, true);
        $corpse = new Mobj(3004, 120.0, 200.0, 0.0, 0.0, 'POSS', 'N', false, 20.0, 56.0, true);
        $corpse->isCorpse = true;
        $world->things->add($barrel);
        $world->things->add($corpse);

        $builder = new CasterBuilder($this->getRenderData()->lights->lampThing);
        [$casters, $count] = $builder->build($this->present($world));

        $expected = 0;

        foreach ($world->things as $thing) {
            if ($thing->isSolid && !$thing->isCorpse && $thing->type !== $this->getRenderData()->lights->lampThing) {
                ++$expected;
            }
        }

        self::assertSame($expected, $count);
        self::assertSame($count * ShadowScene::CASTER_SIZE, \strlen($casters));
        self::assertGreaterThan(1, $count);
    }

    /**
     * @return list<float> the position, the radius, the color
     */
    private function readLight(string $lights, int $index): array
    {
        $values = \unpack('g7', $lights, $index * ShadowScene::LIGHT_SIZE);
        \assert(\is_array($values));

        return \array_values(\array_map(static fn(mixed $value): float => (float) $value, $values));
    }

    private function createE1M1World(): World
    {
        $pathname = \dirname(__DIR__, 3) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        $wad = Wad::open($pathname);

        return $this->createWorld(
            Level::createFromMap($wad->readMap('E1M1')),
            \array_map(strval(...), \array_keys($wad->readTextureDefinitions())),
        );
    }

    private function createBuilder(): LightBuilder
    {
        $data = $this->getRenderData();

        return new LightBuilder($data->lights, $data->limits);
    }
}
