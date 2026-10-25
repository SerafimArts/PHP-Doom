<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Session;

use Local\GPU\DeviceOptions;
use Local\GPU\Resource\Transfer;
use Local\GPU\Window\Window;
use Local\WAD\Wad;
use Serafim\Doom\Command\Input\DeviceFactory;
use Serafim\Doom\Command\Input\OptionReader;
use Serafim\Doom\Game\GameData;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Render\Atlas\LevelAtlasFactory;
use Serafim\Doom\Render\Data\RenderData;
use Serafim\Doom\Render\Hud\GraphicsSet;
use Serafim\Doom\Render\LevelRenderer;
use Serafim\Doom\Render\Sprite\SpriteSet;

/**
 * Opens the WAD, the level, the device and the renderer the way the commands that draw a level need them.
 */
final readonly class ViewerBootstrap
{
    public function __construct(
        private string $shaderDirectory,
    ) {}

    /**
     * @param non-empty-string $applicationName
     * @param int<1, 4294967295> $canvasWidth
     * @param int<1, 4294967295> $canvasHeight
     * @param (\Closure(string): void)|null $onValidationMessage receives the messages of the validation layer
     */
    public function create(
        OptionReader $options,
        ?Window $window,
        string $applicationName,
        int $canvasWidth,
        int $canvasHeight,
        ?\Closure $onValidationMessage = null,
    ): Viewer {
        $wad = Wad::open($options->getString('wad'));
        $level = Level::createFromMap($wad->readMap($options->getString('map')));

        $device = new DeviceFactory()->create(
            $window,
            new DeviceOptions(
                applicationName: $applicationName,
                isValidationEnabled: $options->getBool('validate'),
                onValidationMessage: $onValidationMessage,
            ),
            $options->getRayMode(),
        );

        $data = GameData::load();
        $render = RenderData::load();
        $sprites = SpriteSet::create($wad, $render->sprites->frames);
        $graphics = GraphicsSet::create($wad, $render->graphics);
        $atlas = new LevelAtlasFactory($data->animations)->create($wad, $level, $sprites, $graphics);

        $renderer = new LevelRenderer(
            $device,
            new Transfer($device),
            $level,
            $atlas,
            $wad->readPalettes(),
            $wad->readColormap(),
            $sprites,
            $render->limits,
            $this->shaderDirectory,
            $canvasWidth,
            $canvasHeight,
        );

        return new Viewer($wad, $level, $data, $render, $device, $sprites, $graphics, $atlas, $renderer, $window);
    }
}
