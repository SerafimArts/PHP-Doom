<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Local\WAD\Wad;
use Serafim\Doom\Command\Session\ScenePresenter;
use Serafim\Doom\Game\GameContext;
use Serafim\Doom\Game\GameData;
use Serafim\Doom\Game\Player;
use Serafim\Doom\Game\PlayerStats;
use Serafim\Doom\Game\Random;
use Serafim\Doom\Game\ThingRegistry;
use Serafim\Doom\Game\World;
use Serafim\Doom\Game\Thing\MobjStates;
use Serafim\Doom\Game\Thing\ThingSpawner;
use Serafim\Doom\Game\WorldFactory;
use Serafim\Doom\Level\Data\DataReader;
use Serafim\Doom\Level\Level;
use Serafim\Doom\Level\LevelState;
use Serafim\Doom\Level\SectorState;
use Serafim\Doom\Render\Data\RenderData;
use Serafim\Doom\Render\Scene\SceneState;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var array<string, GameData>
     */
    private static array $gameData = [];

    private static ?RenderData $renderData = null;

    private static ?Level $level = null;

    /**
     * @var list<string>|null
     */
    private static ?array $textureNames = null;

    /**
     * The tables of the game from a data directory (the one of the project by default), loaded once per directory.
     */
    protected function getGameData(?string $directory = null): GameData
    {
        $reader = DataReader::createDefault();

        if ($directory !== null) {
            $reader = new DataReader($directory);
        }

        return self::$gameData[$reader->directory] ??= GameData::load($reader);
    }

    /**
     * The tables of the renderer of the project, loaded once.
     */
    protected function getRenderData(): RenderData
    {
        return self::$renderData ??= RenderData::load();
    }

    /**
     * Puts a level into play with the tables of the game.
     *
     * @param list<string> $textureNames
     */
    protected function createWorld(Level $level, array $textureNames = [], int $seed = 1, int $skill = 3): World
    {
        return new WorldFactory($this->getGameData())->create($level, $textureNames, $seed, $skill);
    }

    /**
     * The shared pieces of a level without any things: the player stands at the origin.
     */
    protected function createContext(Level $level, ?SectorState $sectors = null, int $seed = 1): GameContext
    {
        $data = $this->getGameData();
        $states = new MobjStates($data->mobjStates);

        return new GameContext(
            $level,
            $sectors ?? LevelState::createFromMap($level->map)->sectors,
            new Random($seed),
            $states,
            new ThingSpawner($states, $data->things),
            new ThingRegistry(),
            new Player(0.0, 0.0, 0.0, 0.0),
            new PlayerStats(),
        );
    }

    /**
     * E1M1 of the shareware WAD, loaded once. The test is skipped when the WAD is missing.
     */
    protected function getLevel(): Level
    {
        if (self::$level !== null) {
            return self::$level;
        }

        $pathname = \dirname(__DIR__) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        return self::$level = Level::createFromMap(Wad::open($pathname)->readMap('E1M1'));
    }

    /**
     * A spawner of things with its own state machine and no level.
     */
    protected function createSpawner(): ThingSpawner
    {
        $data = $this->getGameData();

        return new ThingSpawner(new MobjStates($data->mobjStates), $data->things);
    }

    /**
     * The names of the wall textures of the shareware WAD.
     *
     * @return list<string>
     */
    protected function getTextureNames(): array
    {
        if (self::$textureNames !== null) {
            return self::$textureNames;
        }

        $this->getLevel();

        return self::$textureNames = \array_map(
            strval(...),
            \array_keys(Wad::open(\dirname(__DIR__) . '/var/wad/doom1.wad')->readTextureDefinitions()),
        );
    }

    /**
     * What the renderer sees of a world.
     */
    protected function present(World $world): SceneState
    {
        return new ScenePresenter($world->level, $this->getGameData()->weapons)->present($world);
    }
}
