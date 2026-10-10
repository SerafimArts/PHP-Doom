<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Level\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Exception\DataFileException;
use Serafim\Doom\Level\Data\DataReader;
use Serafim\Doom\Level\Data\DataRecord;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(DataReader::class)]
#[CoversClass(DataRecord::class)]
#[CoversClass(DataFileException::class)]
final class DataReaderTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = \sys_get_temp_dir() . '/doom-data-' . \uniqid('', true);

        \mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->directory . '/*') ?: [] as $file) {
            \unlink($file);
        }

        \rmdir($this->directory);
    }

    public function testReadingAFileGivesTypedAccessToItsFields(): void
    {
        $this->write('table.json', '{"name": "imp", "health": 60, "speed": 8, "solid": true, "list": ["a", "b"]}');

        $record = new DataReader($this->directory)->read('table.json');

        self::assertSame('imp', $record->getString('name'));
        self::assertSame(60, $record->getInt('health'));
        self::assertSame(8.0, $record->getFloat('speed'));
        self::assertTrue($record->findBool('solid'));
        self::assertFalse($record->findBool('missing'));
        self::assertSame(5, $record->findInt('missing', 5));
        self::assertNull($record->findString('missing'));
        self::assertSame(['a', 'b'], $record->getRecord('list')->getStrings());
    }

    public function testMissingFileThrowsDataFileException(): void
    {
        $this->expectException(DataFileException::class);
        $this->expectExceptionMessage('cannot be read');

        new DataReader($this->directory)->read('missing.json');
    }

    public function testMalformedJsonThrowsDataFileException(): void
    {
        $this->write('broken.json', '{"name": ');

        $this->expectException(DataFileException::class);
        $this->expectExceptionMessage('not valid JSON');

        new DataReader($this->directory)->read('broken.json');
    }

    public function testJsonThatIsNotAnObjectOrAListThrowsDataFileException(): void
    {
        $this->write('scalar.json', '42');

        $this->expectException(DataFileException::class);

        new DataReader($this->directory)->read('scalar.json');
    }

    public function testFieldOfTheWrongTypeNamesItsPathInTheException(): void
    {
        $this->write('table.json', '{"imp": {"health": "sixty"}}');

        $record = new DataReader($this->directory)->read('table.json');

        $this->expectException(DataFileException::class);
        $this->expectExceptionMessage('"imp.health"');

        $record->getRecord('imp')->getInt('health');
    }

    public function testDefaultReaderPointsAtTheDataDirectoryOfTheProject(): void
    {
        $record = DataReader::createDefault()->read('tuning.json');

        self::assertTrue($record->has('physics'));
    }

    private function write(string $file, string $json): void
    {
        \file_put_contents($this->directory . '/' . $file, $json);
    }
}
