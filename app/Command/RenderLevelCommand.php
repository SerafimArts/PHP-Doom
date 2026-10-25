<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Local\WAD\Map\MapData;
use Local\WAD\Map\Thing;
use Serafim\Doom\Command\Input\OptionReader;
use Serafim\Doom\Command\Png\PngEncoder;
use Serafim\Doom\Command\Session\ScenePresenter;
use Serafim\Doom\Command\Session\ViewerBootstrap;
use Serafim\Doom\Game\WorldFactory;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Render\Camera;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Draws one frame of a level without a window and writes it as a PNG: the way to look at the renderer from a given
 * viewpoint. The picture is stretched to the 4:3 proportion of the original screen.
 */
#[AsCommand(name: 'render-level', description: 'Render a frame of a level to a PNG file (var/dump/frame.png)')]
final class RenderLevelCommand extends Command
{
    private const float EYE_HEIGHT = 41.0;

    protected function configure(): void
    {
        $root = \dirname(__DIR__, 2);

        $this->addOption(
            'wad',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to the WAD file',
            $root . '/var/wad/doom1.wad',
        );
        $this->addOption('map', null, InputOption::VALUE_REQUIRED, 'Level to draw', 'E1M1');
        $this->addOption('x', null, InputOption::VALUE_REQUIRED, 'Eye x in map units (the player start by default)');
        $this->addOption('y', null, InputOption::VALUE_REQUIRED, 'Eye y in map units');
        $this->addOption('z', null, InputOption::VALUE_REQUIRED, 'Eye height (41 above the floor by default)');
        $this->addOption('angle', null, InputOption::VALUE_REQUIRED, 'Direction in degrees, 0 is east, 90 is north');
        $this->addOption('width', null, InputOption::VALUE_REQUIRED, 'Width of the canvas', '640');
        $this->addOption('height', null, InputOption::VALUE_REQUIRED, 'Height of the canvas', '400');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'PNG file', $root . '/var/dump/frame.png');
        $this->addOption('validate', null, InputOption::VALUE_NONE, 'Enable the Vulkan validation layer');
        $this->addOption(
            'rays',
            null,
            InputOption::VALUE_NONE,
            'Require ray traced lighting (fails on a GPU without ray queries, it is automatic by default)',
        );
        $this->addOption('no-rays', null, InputOption::VALUE_NONE, 'Use the plain light of the sectors only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $options = new OptionReader($input);

        $width = $options->getDimension('width');
        $height = $options->getDimension('height');

        $messages = [];
        $started = \microtime(true);
        $viewer = new ViewerBootstrap(\dirname(__DIR__, 2) . '/resources/shaders')->create(
            $options,
            null,
            'render-level',
            $width,
            $height,
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
                \fwrite(\STDERR, $message . "\n");
            },
        );
        $loaded = \microtime(true) - $started;

        $level = $viewer->level;
        $renderer = $viewer->renderer;
        $device = $viewer->device;
        $camera = $this->createCamera($options, $level);

        $started = \microtime(true);
        $textures = \array_map(strval(...), \array_keys($viewer->wad->readTextureDefinitions()));
        $world = new WorldFactory($viewer->data)->create($level, $textures);
        $state = new ScenePresenter($level, $viewer->data->weapons)->present($world);
        $scene = $viewer->createSceneBuilder()->build($state, 0.0, false);
        $pixels = $renderer->render($camera, $world->state, 0.0, $scene);
        $rendered = \microtime(true) - $started;

        $stretched = $this->stretch($pixels, $width, $height);
        $pathname = $options->getString('output');
        new Filesystem()->dumpFile($pathname, PngEncoder::encode($width, $stretched[0], $stretched[1]));

        $io->definitionList(
            ['Camera' => \sprintf(
                'x %.0f, y %.0f, z %.0f, angle %.0f deg',
                $camera->x,
                $camera->y,
                $camera->z,
                \rad2deg($camera->yaw),
            )],
            ['GPU' => $device->getDeviceName()],
            ['Light' => $renderer->describeLighting()],
            ['Time' => \sprintf('loaded in %.0f ms, first frame in %.0f ms', $loaded * 1000, $rendered * 1000)],
            ['Image' => \sprintf('%s (%dx%d)', $pathname, $width, $stretched[0])],
        );

        if ($messages !== []) {
            $io->warning(\array_slice($messages, 0, 5));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function createCamera(OptionReader $options, Level $level): Camera
    {
        $start = $this->findPlayerStart($level->map);
        $x = $options->findFloat('x') ?? (float) ($start->x ?? 0);
        $y = $options->findFloat('y') ?? (float) ($start->y ?? 0);
        $angle = $options->findFloat('angle') ?? (float) ($start->angle ?? 0);

        $floor = $level->map->sectors[$level->findSector((int) $x, (int) $y)]->floorHeight;
        $z = $options->findFloat('z') ?? $floor + self::EYE_HEIGHT;

        return new Camera($x, $y, $z, \deg2rad($angle));
    }

    private function findPlayerStart(MapData $map): ?Thing
    {
        foreach ($map->things as $thing) {
            if ($thing->type === 1) {
                return $thing;
            }
        }

        return null;
    }

    /**
     * Stretches the picture vertically by 1.2 (nearest neighbour), the pixels of Doom are taller than wide.
     *
     * @return array{int<1, max>, string}
     */
    private function stretch(string $pixels, int $width, int $height): array
    {
        $target = (int) \round($height * 1.2);
        $rows = \str_split($pixels, \max(1, $width * 4));
        $data = '';

        for ($y = 0; $y < $target; ++$y) {
            $data .= $rows[\min($height - 1, (int) \floor($y / 1.2))];
        }

        return [\max(1, $target), $data];
    }
}
