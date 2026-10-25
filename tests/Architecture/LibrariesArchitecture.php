<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final readonly class LibrariesArchitecture
{
    public function testLocalLibrariesDoNotDependOnApplication(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Local'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Serafim\Doom'));
    }

    public function testWadLibraryDoesNotDependOnVulkanAndGlfw(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Local\WAD'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Serafim\Vulkan'),
                Selector::inNamespace('Local\GLFW'),
                Selector::inNamespace('Local\GPU'),
            );
    }
}
