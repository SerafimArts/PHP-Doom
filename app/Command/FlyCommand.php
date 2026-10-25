<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Local\GLFW\Constants\Key;
use Local\GPU\Frame\CanvasPresenter;
use Local\GPU\Frame\FrameLoop;
use Local\GPU\Frame\FrameTarget;
use Local\GPU\Window\Window;
use Local\GPU\Window\WindowOptions;
use Serafim\Doom\Command\Input\InputConfig;
use Serafim\Doom\Command\Input\OptionReader;
use Serafim\Doom\Command\Play\KeyAction;
use Serafim\Doom\Command\Session\FpsTitle;
use Serafim\Doom\Command\Session\MouseCapture;
use Serafim\Doom\Command\Session\ViewerBootstrap;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LevelState;
use Serafim\Doom\Render\Camera;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Flies through a level with a free camera: WASD move, the mouse turns, SPACE and LEFT CTRL go up and down, LEFT SHIFT
 * is faster, TAB releases the mouse, ESC leaves.
 */
#[AsCommand(name: 'fly', description: 'Fly through a level with a free camera in a window')]
final class FlyCommand extends Command
{
    private const float SPEED = 320.0;

    private const float FAST_FACTOR = 3.0;

    private const float MOUSE_SENSITIVITY = 0.0025;

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
        $this->addOption('map', null, InputOption::VALUE_REQUIRED, 'Level to fly through', 'E1M1');
        $this->addOption('width', null, InputOption::VALUE_REQUIRED, 'Width of the window', '1280');
        $this->addOption('height', null, InputOption::VALUE_REQUIRED, 'Height of the window', '960');
        $this->addOption('canvas-width', null, InputOption::VALUE_REQUIRED, 'Width of the rendered picture', '640');
        $this->addOption('canvas-height', null, InputOption::VALUE_REQUIRED, 'Height of the rendered picture', '400');
        $this->addOption('seconds', null, InputOption::VALUE_REQUIRED, 'Leave after this time (0 is never)', '0');
        $this->addOption('no-vsync', null, InputOption::VALUE_NONE, 'Do not wait for the vertical blank');
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
        $renderer = $viewer->renderer;
        $device = $viewer->device;

        $loop = new FrameLoop(
            $device,
            static function (FrameTarget $target) use ($device, $renderer): void {
                $renderer->record($target->commandBuffer, $target->frameIndex);
                CanvasPresenter::record($device->vk, $target, $renderer->canvas);
            },
            !$options->getBool('no-vsync'),
        );

        $camera = $this->createCamera($viewer->level);
        $state = LevelState::createFromMap($viewer->level->map);
        $limit = $options->findFloat('seconds') ?? 0.0;

        $input = InputConfig::load();

        $io->writeln(\sprintf(
            '<info>%s</info>: %s (lighting: %s)',
            $device->getDeviceName(),
            $input->help['fly'],
            $renderer->describeLighting(),
        ));

        $mouse = new MouseCapture($window);
        $title = new FpsTitle($window);
        $previous = $window->getTime();
        $total = 0;

        while (!$window->shouldClose() && ($limit === 0.0 || $window->getTime() < $limit)) {
            $window->pollEvents();

            foreach ($window->takeKeyEvents() as $event) {
                if (!$event->isPress()) {
                    continue;
                }

                match ($input->bindings->findAction($event->key)) {
                    KeyAction::LEAVE => $window->requestClose(),
                    KeyAction::TOGGLE_RAYS => $io->writeln($renderer->toggleRayLight()),
                    KeyAction::TOGGLE_MOUSE => $mouse->toggle(),
                    default => null,
                };
            }

            $now = $window->getTime();
            $step = \min(0.1, $now - $previous);
            $previous = $now;

            $camera->yaw -= $mouse->readMotion() * self::MOUSE_SENSITIVITY;
            $this->move($window, $camera, $step);

            $frame = $loop->beginFrame();

            if ($frame === null) {
                $window->waitEvents(0.01);

                continue;
            }

            $renderer->update($frame->index, $camera, $state, $now);
            $loop->endFrame($frame);

            ++$total;
            $title->tick($now, static fn(): string => \sprintf(
                '%s - x %.0f y %.0f z %.0f angle %.0f',
                $renderer->describeLighting(),
                $camera->x,
                $camera->y,
                $camera->z,
                \fmod(\rad2deg($camera->yaw) + 3600, 360),
            ));
        }

        $loop->waitIdle();
        $io->writeln(\sprintf('%d frames, PHP memory peak %.1f MB', $total, \memory_get_peak_usage() / 1e6));

        return self::SUCCESS;
    }

    private function move(Window $window, Camera $camera, float $step): void
    {
        $speed = self::SPEED * $step;

        if ($window->isKeyDown(Key::LEFT_SHIFT)) {
            $speed *= self::FAST_FACTOR;
        }

        $forward = 0.0;
        $side = 0.0;
        $lift = 0.0;

        if ($window->isKeyDown(Key::W)) {
            $forward += 1.0;
        }

        if ($window->isKeyDown(Key::S)) {
            $forward -= 1.0;
        }

        if ($window->isKeyDown(Key::D)) {
            $side += 1.0;
        }

        if ($window->isKeyDown(Key::A)) {
            $side -= 1.0;
        }

        if ($window->isKeyDown(Key::SPACE)) {
            $lift += 1.0;
        }

        if ($window->isKeyDown(Key::LEFT_CONTROL)) {
            $lift -= 1.0;
        }

        if ($window->isKeyDown(Key::LEFT)) {
            $camera->yaw += 2.0 * $step;
        }

        if ($window->isKeyDown(Key::RIGHT)) {
            $camera->yaw -= 2.0 * $step;
        }

        $camera->x += (\cos($camera->yaw) * $forward + \sin($camera->yaw) * $side) * $speed;
        $camera->y += (\sin($camera->yaw) * $forward - \cos($camera->yaw) * $side) * $speed;
        $camera->z += $lift * $speed;
    }

    private function createCamera(Level $level): Camera
    {
        foreach ($level->map->things as $thing) {
            if ($thing->type === 1) {
                $floor = $level->map->sectors[$level->findSector($thing->x, $thing->y)]->floorHeight;

                return new Camera($thing->x, $thing->y, $floor + self::EYE_HEIGHT, \deg2rad($thing->angle));
            }
        }

        return new Camera();
    }
}
