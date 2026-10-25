<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Command\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Serafim\Doom\Command\Input\OptionReader;
use Serafim\Doom\Command\Input\RayMode;
use Serafim\Doom\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

#[CoversClass(OptionReader::class)]
final class OptionReaderTest extends TestCase
{
    /**
     * @param array<string, true> $arguments
     */
    #[DataProvider('rayModeDataProvider')]
    public function testRayModeFollowsTheRaysOptions(array $arguments, RayMode $expected): void
    {
        $input = new ArrayInput($arguments, new InputDefinition([
            new InputOption('rays', null, InputOption::VALUE_NONE),
            new InputOption('no-rays', null, InputOption::VALUE_NONE),
        ]));

        self::assertSame($expected, new OptionReader($input)->getRayMode());
    }

    /**
     * @return iterable<non-empty-string, array{array<string, true>, RayMode}>
     */
    public static function rayModeDataProvider(): iterable
    {
        yield 'no options is automatic' => [[], RayMode::AUTO];
        yield 'rays are required' => [['--rays' => true], RayMode::REQUIRED];
        yield 'no rays turns them off' => [['--no-rays' => true], RayMode::OFF];
        yield 'no rays wins over rays' => [['--rays' => true, '--no-rays' => true], RayMode::OFF];
    }
}
