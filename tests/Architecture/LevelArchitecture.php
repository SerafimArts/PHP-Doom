<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final readonly class LevelArchitecture
{
    public function testLevelDoesNotKnowAboutTheGpuAndTheGame(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Serafim\Doom\Level'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Serafim\Vulkan'),
                Selector::inNamespace('Local\GLFW'),
                Selector::inNamespace('Local\GPU'),
                Selector::inNamespace('Serafim\Doom\Render'),
                Selector::inNamespace('Serafim\Doom\Game'),
            );
    }
}
