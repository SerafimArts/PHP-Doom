<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final readonly class RenderArchitecture
{
    public function testRenderDoesNotKnowAboutTheGameAndTheCommands(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Serafim\Doom\Render'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Serafim\Doom\Game'),
                Selector::inNamespace('Serafim\Doom\Command'),
            );
    }
}
