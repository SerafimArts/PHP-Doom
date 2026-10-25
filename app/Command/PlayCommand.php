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
 * LEFT SHIFT runs, TAB releases the mouse, ESC leaves. The exit switch ends the level, the player who dies starts
 * again.
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

        $viewer = new ViewerBootstrap(\dirname(__DIR__, 2) . '/resources/shaders')->create(
            $options,
            $window,
            'Doom',
            $options->getDimension('canvas-width'),
            $options->getDimension('canvas-height'),
        );

        new PlaySession(
            $viewer,
            $window,
            \array_map(strval(...), \array_keys($viewer->wad->readTextureDefinitions())),
            !$options->getBool('no-vsync'),
            $options->findFloat('seconds') ?? 0.0,
            $io,
            $options->getBool('debug-collision'),
        )->run();

        return self::SUCCESS;
    }
}
