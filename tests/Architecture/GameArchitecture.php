<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final readonly class GameArchitecture
{
    public function testGameDoesNotKnowAboutTheRendererAndTheGpu(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Serafim\Doom\Game'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Serafim\Vulkan'),
                Selector::inNamespace('Local\GLFW'),
                Selector::inNamespace('Local\GPU'),
                Selector::inNamespace('Serafim\Doom\Render'),
                Selector::inNamespace('Serafim\Doom\Command'),
            );
    }
}
