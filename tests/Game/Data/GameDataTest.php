<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Game\Data;

use Local\WAD\Wad;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Game\Combat\PspriteAction;
use Serafim\Doom\Game\Data\PickupCatalog;
use Serafim\Doom\Game\Data\SpecialTable;
use Serafim\Doom\Game\Data\StateData;
use Serafim\Doom\Game\Data\StateTable;
use Serafim\Doom\Game\Data\Tuning;
use Serafim\Doom\Game\Data\WeaponCatalog;
use Serafim\Doom\Game\GameData;
use Serafim\Doom\Game\Thing\ThingCatalog;
use Serafim\Doom\Game\Weapon;
use Serafim\Doom\Level\SectorSpecial;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(GameData::class)]
#[CoversClass(StateTable::class)]
#[CoversClass(WeaponCatalog::class)]
#[CoversClass(ThingCatalog::class)]
#[CoversClass(PickupCatalog::class)]
#[CoversClass(SpecialTable::class)]
#[CoversClass(Tuning::class)]
final class GameDataTest extends TestCase
{
    public function testEveryStateLeadsToAnExistingStateOrEndsTheThing(): void
    {
        $data = $this->getGameData();

        foreach ([$data->mobjStates, $data->weaponStates] as $table) {
            foreach ($table->getAll() as $state) {
                self::assertNotSame('', $state->sprite);

                if ($state->nextName === null) {
                    self::assertNull($table->getNext($state), $state->name);

                    continue;
                }

                self::assertSame($state->nextName, $table->getNext($state)?->name, $state->name);
            }
        }
    }

    public function testStateThatLastsForeverIsNeverLeft(): void
    {
        foreach ($this->getGameData()->mobjStates->getAll() as $state) {
            if ($state->tics === -1) {
                self::assertNull($state->nextName, $state->name . ' lasts for ever and has a next state');
            }
        }
    }

    #[Group('wad')]
    public function testEverySpriteFrameOfTheStatesExistsInTheWad(): void
    {
        $pathname = \dirname(__DIR__, 3) . '/var/wad/doom1.wad';

        if (!\is_file($pathname)) {
            self::markTestSkipped('var/wad/doom1.wad is missing, run `php bin/doom download`');
        }

        $lumps = Wad::open($pathname)->listSprites();
        $data = $this->getGameData();

        $states = [...$data->mobjStates->getAll(), ...$data->weaponStates->getAll()];

        foreach ($states as $state) {
            if ($state->tics === 0) {
                continue;
            }

            $found = false;

            foreach ($lumps as $lump) {
                if (\substr($lump, 0, 4) !== $state->sprite) {
                    continue;
                }

                if ($lump[4] === $state->frame || (\strlen($lump) >= 8 && $lump[6] === $state->frame)) {
                    $found = true;

                    break;
                }
            }

            self::assertTrue(
                $found,
                \sprintf('the sprite %s has no lump for the frame %s', $state->sprite, $state->frame),
            );
        }
    }

    public function testEveryThingRefersToExistingStatesItemsAndDrops(): void
    {
        $data = $this->getGameData();

        foreach ($data->things->getAll() as $name => $thing) {
            self::assertSame($name, $thing->name);

            if ($thing->dropName !== null) {
                self::assertSame($thing->dropName, $data->things->getByName($thing->dropName)->name);
            }

            if ($thing->isShootable) {
                self::assertGreaterThan(0, $thing->health, $name);
                self::assertNotNull($thing->deathState, $name);
            }

            if ($thing->isMissile) {
                self::assertGreaterThan(0.0, $thing->speed, $name);
            }
        }
    }

    public function testThingsAreFoundByTheirNumberInTheMap(): void
    {
        $things = $this->getGameData()->things;

        self::assertSame('POSSESSED', $things->findByType(3004)?->name);
        self::assertSame('BARREL', $things->findByType(2035)?->name);
        self::assertNull($things->findByType(99999));
        self::assertLessThan(0, $things->getByName('PUFF')->type, 'a thing that only the game makes has no number');
    }

    public function testItemsOfTheLevelHaveAPickupWithTheKindTheyNeed(): void
    {
        $data = $this->getGameData();

        foreach ($data->pickups->getAll() as $name => $pickup) {
            self::assertNotSame('', $pickup->message, $name);

            match ($pickup->kind->value) {
                'ammo' => self::assertNotNull($pickup->ammo, $name),
                'armor' => self::assertNotNull($pickup->armor, $name),
                'weapon' => self::assertNotNull($pickup->weapon, $name),
                default => self::assertGreaterThan(0, $pickup->limit, $name),
            };
        }

        self::assertSame('CLIP', $data->things->findByType(2007)?->pickup?->name);
    }

    public function testEveryWeaponHasItsStatesAndAmmunition(): void
    {
        $catalog = $this->getGameData()->weapons;

        foreach (Weapon::cases() as $weapon) {
            $info = $catalog->get($weapon);

            self::assertSame($weapon, $info->weapon);
            self::assertSame(PspriteAction::READY, $info->ready->action);
            self::assertSame(PspriteAction::RAISE, $info->up->action);
            self::assertSame(PspriteAction::LOWER, $info->down->action);
        }

        self::assertNull($catalog->get(Weapon::FIST)->ammo);
        self::assertNull($catalog->get(Weapon::FIST)->flash);
        self::assertSame('S_SGUNFLASH1', $catalog->get(Weapon::SHOTGUN)->flash?->name);
    }

    public function testSectorSpecialsHurtTheWayTheOriginalGameDoes(): void
    {
        $specials = $this->getGameData()->specials;

        self::assertSame(10, $specials->getSectorDamage(SectorSpecial::DAMAGE_10));
        self::assertSame(5, $specials->getSectorDamage(SectorSpecial::DAMAGE_5));
        self::assertSame(20, $specials->getSectorDamage(SectorSpecial::DAMAGE_20));
        self::assertSame(0, $specials->getSectorDamage(SectorSpecial::SECRET));
        self::assertSame(2.0, $specials->doorSpeed);
        self::assertSame(150, $specials->doorWait);
    }

    public function testTuningValuesAreInTheirSaneRanges(): void
    {
        $tuning = $this->getGameData()->tuning;

        self::assertGreaterThan(0.0, $tuning->physics->friction);
        self::assertLessThan(1.0, $tuning->physics->friction);
        self::assertGreaterThan(0.0, $tuning->physics->maxStep);
        self::assertGreaterThan($tuning->combat->meleeRange, $tuning->combat->missileRange);
        self::assertSame(7, $tuning->weapon->pellets);
        self::assertGreaterThan($tuning->weapon->top, $tuning->weapon->bottom);
        self::assertEqualsWithDelta(0.7172, $tuning->monster->diagonal, 0.0001);
    }

    public function testStateOfAMissingNameThrowsDataFileException(): void
    {
        $this->expectException(DataFileException::class);

        $this->getGameData()->mobjStates->get('S_NOT_A_STATE');
    }

    public function testDataDirectoryWithAStateThatLeadsNowhereThrowsDataFileException(): void
    {
        $directory = $this->copyDataDirectory();

        $states = \json_decode((string) \file_get_contents($directory . '/mobj-states.json'), true);
        \assert(\is_array($states));
        $states['S_POSS_STND']['next'] = 'S_NOWHERE';
        \file_put_contents($directory . '/mobj-states.json', \json_encode($states));

        try {
            $this->expectException(DataFileException::class);
            $this->expectExceptionMessage('S_NOWHERE');

            $this->getGameData($directory);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    public function testDataDirectoryWithAnUnknownActionThrowsDataFileException(): void
    {
        $directory = $this->copyDataDirectory();

        $states = \json_decode((string) \file_get_contents($directory . '/mobj-states.json'), true);
        \assert(\is_array($states));
        $states['S_POSS_STND']['action'] = 'DANCE';
        \file_put_contents($directory . '/mobj-states.json', \json_encode($states));

        try {
            $this->expectException(DataFileException::class);
            $this->expectExceptionMessage('DANCE');

            $this->getGameData($directory);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function copyDataDirectory(): string
    {
        $directory = \sys_get_temp_dir() . '/doom-gamedata-' . \uniqid('', true);
        \mkdir($directory);

        foreach (\glob(\dirname(__DIR__, 3) . '/resources/data/*.json') ?: [] as $file) {
            \copy($file, $directory . '/' . \basename($file));
        }

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (\glob($directory . '/*') ?: [] as $file) {
            \unlink($file);
        }

        \rmdir($directory);
    }
}
