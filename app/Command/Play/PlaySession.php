<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Play;

use Local\GPU\Frame\CanvasPresenter;
use Local\GPU\Frame\FrameLoop;
use Local\GPU\Frame\FrameTarget;
use Local\GPU\Window\Window;
use Serafim\Doom\Command\Input\InputConfig;
use Serafim\Doom\Command\Input\WindowKeyState;
use Serafim\Doom\Command\Session\FpsTitle;
use Serafim\Doom\Command\Session\MouseCapture;
use Serafim\Doom\Command\Session\ScenePresenter;
use Serafim\Doom\Command\Session\Viewer;
use Serafim\Doom\Game\LevelResult;
use Serafim\Doom\Game\Physics\Collider;
use Serafim\Doom\Game\Physics\CollisionReport;
use Serafim\Doom\Game\Physics\StallDetector;
use Serafim\Doom\Game\PlayerInput;
use Serafim\Doom\Game\TicClock;
use Serafim\Doom\Game\World;
use Serafim\Doom\Game\WorldFactory;
use Serafim\Doom\Render\Camera;
use Serafim\Doom\Render\Scene;
use Serafim\Doom\Render\SceneBuilder;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One game in a window: the tics run at their own rate, the picture is drawn as often as the screen allows with the
 * view interpolated between the tics. The exit switch ends the level, the player who dies starts again.
 */
final class PlaySession
{
    /**
     * Seconds the intermission screen stays before a key closes it
     */
    private const float RESULT_DELAY = 1.0;

    private readonly InputConfig $input;

    private readonly ScenePresenter $presenter;

    private readonly SceneBuilder $sceneBuilder;

    private readonly WorldFactory $worlds;

    private World $world;

    private ?LevelResult $result = null;

    private float $resultShownAt = 0.0;

    private ?StallDetector $stalls = null;

    private readonly CollisionLog $collisionLog;

    /**
     * @param list<string> $textures the names of the wall textures that exist
     * @param float $timeLimit seconds after which the game ends, 0 for never
     */
    public function __construct(
        private readonly Viewer $viewer,
        private readonly Window $window,
        private readonly array $textures,
        private readonly bool $isVsyncEnabled,
        private readonly float $timeLimit,
        private readonly SymfonyStyle $io,
        bool $isCollisionDebugged = false,
    ) {
        $this->collisionLog = new CollisionLog(\dirname(__DIR__, 3) . '/var/log/collision.log');

        if ($isCollisionDebugged) {
            $this->stalls = new StallDetector();
        }

        $this->input = InputConfig::load();
        $this->presenter = new ScenePresenter($viewer->level, $viewer->data->weapons);
        $this->sceneBuilder = $viewer->createSceneBuilder(true);
        $this->worlds = new WorldFactory($viewer->data);
        $this->world = $this->worlds->create($viewer->level, $textures);
    }

    public function run(): void
    {
        $renderer = $this->viewer->renderer;
        $device = $this->viewer->device;
        $window = $this->window;

        $loop = new FrameLoop(
            $device,
            static function (FrameTarget $target) use ($device, $renderer): void {
                $renderer->record($target->commandBuffer, $target->frameIndex);
                CanvasPresenter::record($device->vk, $target, $renderer->canvas);
            },
            $this->isVsyncEnabled,
        );

        $this->io->writeln(\sprintf(
            '<info>%s</info>: %s (lighting: %s)',
            $device->getDeviceName(),
            $this->input->help['play'],
            $renderer->describeLighting(),
        ));

        $keyboard = new KeyboardInput(new WindowKeyState($window), $this->input);
        $mouse = new MouseCapture($window);
        $title = new FpsTitle($window);
        $clock = new TicClock();
        $total = 0;

        while (!$window->shouldClose() && ($this->timeLimit === 0.0 || $window->getTime() < $this->timeLimit)) {
            $window->pollEvents();
            $this->handleKeys($keyboard, $mouse);

            $now = $window->getTime();
            $keyboard->addMouseMotion($mouse->readMotion());

            $this->tick($keyboard, $clock->advance($now), $now);

            $frame = $loop->beginFrame();

            if ($frame === null) {
                $window->waitEvents(0.01);

                continue;
            }

            $view = $this->world->getView($clock->getAlpha());
            $palette = 0;

            if ($this->result === null) {
                $palette = $this->world->stats->feedback->getPalette();
            }

            $renderer->update(
                $frame->index,
                new Camera($view->x, $view->y, $view->z, $view->yaw),
                $this->world->state,
                $now,
                $palette,
                $this->buildScene($now),
            );
            $loop->endFrame($frame);

            ++$total;
            $title->tick($now, $this->describeState(...));
        }

        $loop->waitIdle();
        $this->io->writeln(
            \sprintf(
                '%d frames, %d tics, PHP memory peak %.1f MB',
                $total,
                $this->world->getTic(),
                \memory_get_peak_usage() / 1e6,
            ),
        );
    }

    private function handleKeys(KeyboardInput $keyboard, MouseCapture $mouse): void
    {
        foreach ($this->window->takeKeyEvents() as $event) {
            if (!$event->isPress()) {
                continue;
            }

            if ($this->result !== null && $this->window->getTime() - $this->resultShownAt >= self::RESULT_DELAY) {
                $this->window->requestClose();
            }

            $action = $this->input->bindings->findAction($event->key);

            if ($action === null) {
                continue;
            }

            match ($action) {
                KeyAction::USE => $keyboard->requestUse(),
                KeyAction::WEAPON_1, KeyAction::WEAPON_2, KeyAction::WEAPON_3 => $keyboard->requestWeapon(
                    $action->getWeaponSlot() ?? 1,
                ),
                KeyAction::LEAVE => $this->window->requestClose(),
                KeyAction::TOGGLE_RAYS => $this->io->writeln($this->viewer->renderer->toggleRayLight()),
                KeyAction::TOGGLE_MOUSE => $mouse->toggle(),
            };
        }
    }

    /**
     * @param int<0, max> $tics the tics that the clock owes
     */
    private function tick(KeyboardInput $keyboard, int $tics, float $now): void
    {
        for (; $tics > 0 && $this->result === null; --$tics) {
            $input = $keyboard->read();

            $this->world->tick($input);
            $this->watchStall($input);
        }

        if ($this->result === null && $this->world->isExitRequested()) {
            $this->result = $this->world->getResult();
            $this->resultShownAt = $now;
            $this->io->success($this->describeResult());
        }

        if ($this->result === null && $this->world->isRestartRequested()) {
            $this->world = $this->worlds->create($this->viewer->level, $this->textures);
        }
    }

    /**
     * Writes a report when the player holds a move key and does not move.
     */
    private function watchStall(PlayerInput $input): void
    {
        if ($this->stalls === null) {
            return;
        }

        $player = $this->world->player;
        $isMoveHeld = $input->forwardMove !== 0 || $input->sideMove !== 0;

        if (!$this->stalls->update($player->x, $player->y, $isMoveHeld)) {
            return;
        }

        $world = $this->world;
        $collider = new Collider($this->viewer->level, $world->state->sectors, $world->things);
        $report = new CollisionReport($this->viewer->level, $world->state->sectors, $collider);

        $this->collisionLog->write($report->describe($player, $world->things->all(), $world->getTic()));
        $this->io->warning(\sprintf('The player stands still, see %s', $this->collisionLog->pathname));
    }

    private function buildScene(float $time): Scene
    {
        if ($this->result !== null) {
            return $this->sceneBuilder->buildIntermission($this->presenter->presentResult($this->result));
        }

        return $this->sceneBuilder->build($this->presenter->present($this->world), $time);
    }

    private function describeState(): string
    {
        $player = $this->world->player;

        return \sprintf(
            '%s - health %d - secrets %d/%d - x %.0f y %.0f',
            $this->viewer->renderer->describeLighting(),
            $this->world->stats->health,
            $this->world->stats->secrets,
            $this->world->getTotalSecrets(),
            $player->x,
            $player->y,
        );
    }

    private function describeResult(): string
    {
        $seconds = \intdiv($this->world->getTic(), TicClock::RATE);

        return \sprintf(
            'Level complete: time %d:%02d, secrets %d of %d, health %d',
            \intdiv($seconds, 60),
            $seconds % 60,
            $this->world->stats->secrets,
            $this->world->getTotalSecrets(),
            $this->world->stats->health,
        );
    }
}
