<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render\Data;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Command\BuildShaders\LimitsGenerator;
use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataReader;
use Serafim\Doom\Render\Data\FlameProfile;
use Serafim\Doom\Render\Data\LightProfile;
use Serafim\Doom\Render\Data\Limits;
use Serafim\Doom\Render\Data\RenderData;
use Serafim\Doom\Render\Data\SpriteCatalog;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(RenderData::class)]
#[CoversClass(SpriteCatalog::class)]
#[CoversClass(LightProfile::class)]
#[CoversClass(FlameProfile::class)]
#[CoversClass(Limits::class)]
final class RenderDataTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/doom-render-' . \bin2hex(\random_bytes(4));
        \mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->directory . '/*') ?: [] as $file) {
            @\unlink($file);
        }

        @\rmdir($this->directory);
    }

    public function testLimitsOfTheShadersMatchTheGeneratedGlslFile(): void
    {
        $directory = \dirname(__DIR__, 3) . '/resources/shaders';

        $generated = new LimitsGenerator()->render($directory . '/limits.json');

        self::assertSame($generated, \file_get_contents($directory . '/limits.glsl'));
    }

    public function testProjectFilesLoadWithTheValuesOfTheOriginalStatusBar(): void
    {
        $data = $this->getRenderData();

        self::assertSame(168, $data->statusBar->barTop);
        self::assertSame(143, $data->statusBar->faceX);
        self::assertSame(32, $data->limits->maxLights);
        self::assertSame(31, $data->limits->colormapLastRow);
        self::assertSame(35.0, $data->limits->ticsPerSecond);
        self::assertCount(4, $data->statusBar->ammoRows->rows);
        self::assertCount(8, $data->face->look);
        self::assertSame(3599, $data->intermission->longestTime);
    }

    public function testEverySpriteFrameOfTheCatalogHasLetters(): void
    {
        foreach ($this->getRenderData()->sprites->frames as $sprite => $frames) {
            self::assertMatchesRegularExpression('/^[A-Z]+$/', $frames, $sprite);
            self::assertSame(4, \strlen($sprite));
        }
    }

    public function testLampOfTheLightsIsAThingOfTheGame(): void
    {
        $lamp = $this->getGameData()->things->findByType($this->getRenderData()->lights->lampThing);

        self::assertNotNull($lamp);
    }

    public function testEveryFlameSpriteIsInTheCatalogOfSprites(): void
    {
        $data = $this->getRenderData();

        self::assertArrayHasKey($data->lights->fireball->sprite, $data->sprites->frames);
        self::assertArrayHasKey($data->lights->barrel->sprite, $data->sprites->frames);

        foreach ([$data->lights->fireball, $data->lights->barrel] as $flame) {
            foreach (\array_keys($flame->frames) as $letter) {
                self::assertStringContainsString($letter, $data->sprites->frames[$flame->sprite]);
            }
        }
    }

    #[Group('wad')]
    public function testEveryGraphicOfTheCatalogIsInTheWad(): void
    {
        $pathname = \dirname(__DIR__, 3) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        $wad = Wad::open($pathname);
        $names = $this->getRenderData()->graphics->names;

        self::assertNotEmpty($names);

        foreach ($names as $name) {
            self::assertNotNull($wad->readPicture($name), $name);
        }
    }

    public function testFlameFrameFindsNothingForAnotherSprite(): void
    {
        $fireball = $this->getRenderData()->lights->fireball;

        self::assertNotNull($fireball->findFrame($fireball->sprite, 'A'));
        self::assertNull($fireball->findFrame('POSS', 'A'));
        self::assertNull($fireball->findFrame($fireball->sprite, 'Z'));
    }

    public function testMissingFileThrowsDataFileException(): void
    {
        $this->expectException(DataFileException::class);

        RenderData::load(new DataReader($this->directory), new DataReader($this->directory));
    }

    public function testMalformedLimitsThrowDataFileException(): void
    {
        \file_put_contents(
            $this->directory . '/limits.json',
            '{"maxLights": 0, "colormapLastRow": 31, "ticsPerSecond": 35.0}',
        );

        $this->expectException(DataFileException::class);

        Limits::createFromRecord(new DataReader($this->directory)->read('limits.json'));
    }
}
