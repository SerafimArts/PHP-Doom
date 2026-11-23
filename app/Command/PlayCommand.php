<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Local\GPU\Window\Window;
use Local\GPU\Window\WindowOptions;
use Serafim\Doom\Command\Input\OptionReader;
use Serafim\Doom\Command\Play\PlaySession;
use Serafim\Doom\Command\Session\ViewerBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Walks through a level: the game runs 35 tics per second, the picture is drawn as often as the screen allows with the
 * view interpolated between the tics. W S A D move, the mouse and the arrows turn, SPACE or E uses doors and switches,
 * LEFT SHIFT runs, TAB releases the mouse, ESC leaves. The exit switch advances to the next map in the WAD,
 * and the player who dies starts the current map again.
 */
#[AsCommand(name: 'play', description: 'Walk through a level in a window')]
final class PlayCommand extends Command
{
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
        $this->addOption('map', null, InputOption::VALUE_REQUIRED, 'Level to play', 'E1M1');
        $this->addOption('width', null, InputOption::VALUE_REQUIRED, 'Width of the window', '1280');
        $this->addOption('height', null, InputOption::VALUE_REQUIRED, 'Height of the window', '960');
        $this->addOption('canvas-width', null, InputOption::VALUE_REQUIRED, 'Width of the rendered picture', '640');
        $this->addOption('canvas-height', null, InputOption::VALUE_REQUIRED, 'Height of the rendered picture', '400');
        $this->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Leave after this time (0 is never)', '0');
        $this->addOption('no-vsync', null, InputOption::VALUE_NONE, 'Do not wait for the vertical blank');
        $this->addOption(
            'debug-collision',
            null,
            InputOption::VALUE_NONE,
            'Log where the player stands when a held move key does not move the player for a second (var/log)',
        );
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

        $window = new Window(new WindowOptions(
            title: 'Doom',
            width: $options->getDimension('width'),
            height: $options->getDimension('height'),
        ));

        $bootstrap = new ViewerBootstrap(\dirname(__DIR__, 2) . '/resources/shaders');
        $canvasWidth = $options->getDimension('canvas-width');
        $canvasHeight = $options->getDimension('canvas-height');
        $viewer = $bootstrap->create(
            $options,
            $window,
            'Doom',
            $canvasWidth,
            $canvasHeight,
        );

        $maps = $viewer->wad->listMaps();
        $textures = \array_map(strval(...), \array_keys($viewer->wad->readTextureDefinitions()));
        $isVsyncEnabled = !$options->getBool('no-vsync');
        $timeLimit = $options->findFloat('seconds') ?? 0.0;
        $isCollisionDebugged = $options->getBool('debug-collision');

        while (true) {
            $map = $viewer->level->map->name;
            $index = \array_search($map, $maps, true);
            $normalDestination = match ($map) {
                'E1M8' => null,
                'E1M9' => \in_array('E1M4', $maps, true) ? 'E1M4' : null,
                default => $index === false ? null : ($maps[$index + 1] ?? null),
            };
            $secretDestination = $map === 'E1M3' && \in_array('E1M9', $maps, true) ? 'E1M9' : null;

            $nextMap = new PlaySession(
                $viewer,
                $window,
                $textures,
                $isVsyncEnabled,
                $timeLimit,
                $io,
                $isCollisionDebugged,
                $normalDestination,
                $secretDestination,
            )->run();

            if ($nextMap === null) {
                break;
            }

            $io->writeln(\sprintf('Loading %s', $nextMap));
            $viewer = $bootstrap->changeMap($viewer, $nextMap, $canvasWidth, $canvasHeight);
        }

        return self::SUCCESS;
    }
}
