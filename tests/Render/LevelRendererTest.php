<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Render;

use Local\GPU\Device;
use Local\GPU\DeviceOptions;
use Local\GPU\Resource\Transfer;
use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Command\Input\DeviceFactory;
use Serafim\Doom\Command\Input\RayMode;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\World;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LevelState;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Hud\GraphicsSet;
use Serafim\Doom\Render\Hud\HudBuilder;
use Serafim\Doom\Render\Sprite\SpriteSet;
use Serafim\Doom\Render\Camera;
use Serafim\Doom\Render\LevelRenderer;
use Serafim\Doom\Render\SceneBuilder;
use Serafim\Doom\Render\SceneBuilderFactory;
use Serafim\Doom\Tests\TestCase;

#[Group('gpu')]
#[Group('wad')]
#[CoversClass(LevelRenderer::class)]
final class LevelRendererTest extends TestCase
{
    private const int WIDTH = 160;

    private const int HEIGHT = 100;

    /** @var list<string> */
    private array $messages = [];

    private SceneBuilder $sceneBuilder;

    public function testStartOfTheLevelIsDrawnWithoutValidationMessages(): void
    {
        [$renderer, $camera, $sectors] = $this->createRenderer();

        $pixels = $renderer->render($camera, $sectors);

        self::assertSame(self::WIDTH * self::HEIGHT * 4, \strlen($pixels));
        self::assertGreaterThan(
            0.95,
            $this->getLitShare($pixels),
            'every pixel of the room is covered by a wall or a floor',
        );
        self::assertSame([], $this->messages);
    }

    public function testSkyFillsTheSpaceAboveTheWallsOfTheYard(): void
    {
        [$renderer, , $sectors] = $this->createRenderer();

        $pixels = $renderer->render(new Camera(1510.0, -2940.0, 40.0, \deg2rad(20.0)), $sectors);

        // The top row of a yard view is sky, the bottom row is the ground.
        $top = \substr($pixels, 0, self::WIDTH * 4);

        self::assertGreaterThan(0.9, $this->getLitShare($top));
        self::assertSame([], $this->messages);
    }

    public function testCameraTurnChangesThePicture(): void
    {
        [$renderer, $camera, $sectors] = $this->createRenderer();

        $north = $renderer->render($camera, $sectors);
        $camera->yaw += \M_PI;
        $south = $renderer->render($camera, $sectors);

        self::assertNotSame($north, $south);
    }

    public function testThingsAndTheStatusBarAreDrawnOnTopOfTheLevel(): void
    {
        [$renderer, $camera, , $level, $textures] = $this->createRenderer();

        $world = $this->createWorld($level, $textures);
        $world->player->x = 1056;
        $world->player->y = -3616;

        $bare = $renderer->render($camera, $world->state);
        $full = $renderer->render($camera, $world->state, 0.0, $this->sceneBuilder->build($this->present($world)));

        self::assertNotSame($bare, $full);

        $barRow = \substr($full, (int) (self::HEIGHT * 0.95) * self::WIDTH * 4, self::WIDTH * 4);

        self::assertGreaterThan(0.9, $this->getLitShare($barRow), 'the status bar covers the bottom of the picture');
        self::assertSame([], $this->messages);
    }

    public function testPressedSwitchLooksDifferent(): void
    {
        [$renderer, , , $level, $textures] = $this->createRenderer();

        $world = $this->createWorld($level, $textures);
        $world->player->x = 2975;
        $world->player->y = -4768;
        $world->player->yaw = \M_PI;

        $camera = new Camera(2975.0, -4768.0, 41.0, \M_PI);
        $before = $renderer->render($camera, $world->state);

        $world->tick(new PlayerInput(isUsing: true));
        $after = $renderer->render($camera, $world->state);

        self::assertNotSame($before, $after);
        self::assertSame([], $this->messages);
    }

    public function testRayLightingBrightensTheLampsAndTheToggleTurnsItOff(): void
    {
        [$renderer, , , $level, $textures] = $this->createRenderer(true);

        if (!$renderer->hasRayLighting()) {
            self::markTestSkipped('The GPU has no ray queries');
        }

        $world = $this->createWorld($level, $textures);
        $world->player->x = 3010;
        $world->player->y = -4040;
        $world->player->z = -24.0;
        $world->player->yaw = \deg2rad(270.0);

        $camera = new Camera(3010.0, -4040.0, 17.0, \deg2rad(270.0));
        $scene = $this->sceneBuilder->build($this->present($world), 0.0, false);

        $lit = $renderer->render($camera, $world->state, 0.0, $scene);
        $renderer->isRayLightEnabled = false;
        $plain = $renderer->render($camera, $world->state, 0.0, $scene);

        self::assertNotSame($lit, $plain);
        self::assertGreaterThan($this->getBrightness($plain), $this->getBrightness($lit));
        self::assertSame([], $this->messages);
    }

    public function testClosedDoorKeepsTheLightOfTheLampsInTheRoomBehindIt(): void
    {
        [$renderer, , , $level, $textures] = $this->createRenderer(true);

        if (!$renderer->hasRayLighting()) {
            self::markTestSkipped('The GPU has no ray queries');
        }

        $world = $this->createWorld($level, $textures);
        $camera = new Camera(3008.0, -3860.0, -7.0, \deg2rad(270.0));
        $scene = $this->sceneBuilder->build($this->present($world), 0.0, false);

        $closed = $renderer->render($camera, $world->state, 0.0, $scene);

        $world->state->sectors->ceilingHeights[76] = 44;
        $open = $renderer->render($camera, $world->state, 0.0, $scene);

        self::assertGreaterThan($this->getBrightness($closed), $this->getBrightness($open));
        self::assertSame([], $this->messages);
    }

    /**
     * @return array{LevelRenderer, Camera, LevelState, Level, list<string>}
     */
    private function createRenderer(bool $isRayQueryEnabled = false): array
    {
        $pathname = \dirname(__DIR__, 2) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        $shaders = \dirname(__DIR__, 2) . '/resources/shaders';

        if (!\is_file($shaders . '/wall.vert.spv')) {
            self::markTestSkipped('The shaders are not compiled, run `php bin/doom build-shaders`');
        }

        $wad = Wad::open($pathname);
        $level = Level::createFromMap($wad->readMap('E1M1'));

        try {
            $device = new DeviceFactory()->create(
                null,
                new DeviceOptions(
                    isValidationEnabled: true,
                    onValidationMessage: function (string $message): void {
                        $this->messages[] = $message;
                    },
                ),
                $isRayQueryEnabled ? RayMode::AUTO : RayMode::OFF,
            );
        } catch (\Throwable) {
            $device = new DeviceFactory()->create(
                null,
                new DeviceOptions(),
                $isRayQueryEnabled ? RayMode::AUTO : RayMode::OFF,
            );
        }

        $data = $this->getRenderData();
        $sprites = SpriteSet::create($wad, $data->sprites->frames);
        $graphics = GraphicsSet::create($wad, $data->graphics);
        $atlas = new LevelAtlasFactory($this->getGameData()->animations)->create($wad, $level, $sprites, $graphics);
        $this->sceneBuilder = new SceneBuilderFactory($data, $graphics, $atlas, $sprites)->create();

        $renderer = new LevelRenderer(
            $device,
            new Transfer($device),
            $level,
            $atlas,
            $wad->readPalettes(),
            $wad->readColormap(),
            $sprites,
            $data->limits,
            $shaders,
            self::WIDTH,
            self::HEIGHT,
        );

        return [
            $renderer,
            new Camera(1056.0, -3616.0, 41.0, \M_PI / 2),
            LevelState::createFromMap($level->map),
            $level,
            \array_map(strval(...), \array_keys($wad->readTextureDefinitions())),
        ];
    }

    private function getBrightness(string $pixels): int
    {
        $sum = 0;
        $total = \intdiv(\strlen($pixels), 4);

        for ($i = 0; $i < $total; ++$i) {
            $sum += \ord($pixels[$i * 4]) + \ord($pixels[$i * 4 + 1]) + \ord($pixels[$i * 4 + 2]);
        }

        return $sum;
    }

    private function getLitShare(string $pixels): float
    {
        $lit = 0;
        $total = \intdiv(\strlen($pixels), 4);

        for ($i = 0; $i < $total; ++$i) {
            if ($pixels[$i * 4] !== "\0" || $pixels[$i * 4 + 1] !== "\0" || $pixels[$i * 4 + 2] !== "\0") {
                ++$lit;
            }
        }

        return $lit / $total;
    }
}
