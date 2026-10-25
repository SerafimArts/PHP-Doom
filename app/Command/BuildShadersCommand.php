<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Serafim\Doom\Command\BuildShaders\CompilerLocator;
use Serafim\Doom\Command\BuildShaders\LimitsGenerator;
use Serafim\Doom\Command\BuildShaders\ShaderCompiler;
use Serafim\Doom\Exception\ShaderCompilationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'build-shaders', description: 'Compile resources/shaders/*.{vert,frag,comp} to SPIR-V')]
final class BuildShadersCommand extends Command
{
    private const string DEFAULT_SDK_DIRECTORY = 'D:/Development/VulkanSDK/1.4.363.0/Bin';

    private readonly CompilerLocator $locator;

    public function __construct(
        /**
         * Where the shaders are searched, missing directories are skipped
         *
         * @var list<non-empty-string>
         */
        private readonly array $directories = [
            __DIR__ . '/../../resources/shaders',
            __DIR__ . '/../../libs/gpu/examples/shaders',
        ],
        ?CompilerLocator $locator = null,
    ) {
        parent::__construct();

        $this->locator = $locator ?? new CompilerLocator(directories: [self::DEFAULT_SDK_DIRECTORY]);
    }

    /**
     * @throws ShaderCompilationException in case of the compiler is not found or a shader is invalid
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $directories = \array_values(\array_filter($this->directories, \is_dir(...)));

        if ($directories === []) {
            $io->warning('There are no shaders yet.');

            return self::SUCCESS;
        }

        $compiler = new ShaderCompiler($this->locator->getCompiler());
        $limits = new LimitsGenerator();

        foreach ($directories as $directory) {
            $generated = $limits->generate($directory);

            if ($generated !== null) {
                $io->writeln(\sprintf('<info>ok</info> %s', \basename($generated)));
            }
        }

        $shaders = new Finder()
            ->files()
            ->in($directories)
            ->name(['*.vert', '*.frag', '*.comp'])
            ->sortByName();

        foreach ($shaders as $shader) {
            /** @var non-empty-string $pathname */
            $pathname = $shader->getPathname();
            $target = $compiler->compile($pathname);

            $io->writeln(\sprintf('<info>ok</info> %s (%d bytes)', \basename($target), (int) \filesize($target)));

            $rays = $compiler->compileWithRays($pathname);

            if ($rays !== null) {
                $io->writeln(\sprintf('<info>ok</info> %s (%d bytes)', \basename($rays), (int) \filesize($rays)));
            }
        }

        return self::SUCCESS;
    }
}
