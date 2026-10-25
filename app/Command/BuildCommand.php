<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Serafim\Doom\Command\Build\ComposerManifest;
use Serafim\Doom\Command\Build\FileSelector;
use Serafim\Doom\Command\Build\FolderVerifier;
use Serafim\Doom\Command\Build\GameFolder;
use Serafim\Doom\Command\Build\Launchers;
use Serafim\Doom\Command\Build\OutputGuard;
use Serafim\Doom\Command\Download\ReferenceCatalog;
use Serafim\Doom\Command\Input\OptionReader;
use Serafim\Doom\Exception\BuildException;
use Serafim\Doom\Exception\DownloadException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'build', description: 'Build the distributable game folder for Windows')]
final class BuildCommand extends Command
{
    private const string WAD = 'var/wad/doom1.wad';

    private const int WAD_BYTES_MINIMUM = 1_000_000;

    protected function configure(): void
    {
        $this->addOption(
            'output',
            null,
            InputOption::VALUE_REQUIRED,
            'Directory of the build, inside build/',
            'build/windows',
        );
        $this->addOption('no-wad', null, InputOption::VALUE_NONE, 'Do not put doom1.wad into the build');
        $this->addOption('clean', null, InputOption::VALUE_NEGATABLE, 'Remove the old build first', true);
        $this->addOption('skip-composer', null, InputOption::VALUE_NONE, 'Do not install the packages');
        $this->addOption('skip-run', null, InputOption::VALUE_NONE, 'Do not start the built game to check it');
    }

    /**
     * @throws BuildException in case of the build or its verification fails
     * @throws DownloadException in case of doom1.wad cannot be downloaded
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $options = new OptionReader($input);
        $filesystem = new Filesystem();
        $root = \dirname(__DIR__, 2);

        $requested = $options->getString('output');
        $pathname = new OutputGuard($root)->getSafePath(Path::makeAbsolute($requested, $root));
        $isWadIncluded = $input->getOption('no-wad') !== true;

        if ($input->getOption('clean') === true && \is_dir($pathname)) {
            $io->writeln(\sprintf('<comment>clean</comment> %s', $pathname));
            $filesystem->remove($pathname);
        }

        $folder = new GameFolder($root, $pathname, new FileSelector(), $filesystem);
        $manifest = new ComposerManifest();
        $launchers = new Launchers();

        $io->writeln('<info>copy</info>  app, libs, resources');
        $folder->copySelectedFiles();

        $entryPoint = \file_get_contents($root . '/bin/doom');
        $source = \file_get_contents($root . '/composer.json');
        \assert(\is_string($entryPoint) && \is_string($source));

        $decoded = \json_decode($source, true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded));

        $folder->write('bin/doom', $manifest->createEntryPoint($entryPoint));
        $folder->write(
            'composer.json',
            \json_encode(
                $manifest->createRuntime($decoded),
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
            ) . "\n",
        );
        $folder->write('play.bat', $launchers->createBatch('play'));
        $folder->write('fly.bat', $launchers->createBatch('fly'));
        $folder->write('README.txt', $launchers->createReadme());

        if ($isWadIncluded) {
            $this->putWad($io, $filesystem, $root, $pathname);
        }

        if ($input->getOption('skip-composer') !== true) {
            $this->installPackages($io, $pathname);
        }

        $this->printSizes($io, $folder);

        if ($input->getOption('skip-composer') === true) {
            $io->warning('Composer was skipped: the folder is not verified.');

            return self::SUCCESS;
        }

        new FolderVerifier()->verify($pathname, $isWadIncluded);
        $io->writeln('<info>check</info> required files exist, no development packages');

        if ($input->getOption('skip-run') !== true) {
            $this->runBuiltGame($io, $pathname, $isWadIncluded);
        }

        $io->success(\sprintf('The game is built: %s', $pathname));

        return self::SUCCESS;
    }

    /**
     * @throws DownloadException in case of doom1.wad cannot be downloaded
     * @throws BuildException in case of doom1.wad is missing or broken
     */
    private function putWad(SymfonyStyle $io, Filesystem $filesystem, string $root, string $pathname): void
    {
        $wad = $root . '/' . self::WAD;

        if (!\is_file($wad)) {
            $io->writeln('<info>fetch</info> wad/doom1.wad');

            $catalog = ReferenceCatalog::create($root . '/var', HttpClient::create(), $filesystem);

            foreach ($catalog as $download) {
                if ($download->getName() === 'wad/doom1.wad') {
                    $download->download();
                }
            }
        }

        if (!\is_file($wad) || \filesize($wad) < self::WAD_BYTES_MINIMUM) {
            throw BuildException::becauseSourceIsMissing($wad);
        }

        $io->writeln('<info>copy</info>  ' . self::WAD);
        $filesystem->copy($wad, $pathname . '/' . self::WAD, true);
    }

    /**
     * @throws BuildException in case of composer fails
     */
    private function installPackages(SymfonyStyle $io, string $pathname): void
    {
        $io->writeln('<info>composer</info> install --no-dev --classmap-authoritative');

        $process = Process::fromShellCommandline(
            'composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative',
            $pathname,
            ['COMPOSER_MIRROR_PATH_REPOS' => '1'],
            null,
            600,
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw BuildException::becauseStepFailed(
                'composer install',
                $process->getErrorOutput() . $process->getOutput(),
            );
        }
    }

    /**
     * @throws BuildException in case of the built game does not start
     */
    private function runBuiltGame(SymfonyStyle $io, string $pathname, bool $isWadIncluded): void
    {
        $commands = [['list']];

        if ($isWadIncluded) {
            $commands[] = ['play', '--seconds=2'];
        }

        foreach ($commands as $arguments) {
            $io->writeln('<info>run</info>   php bin/doom ' . \implode(' ', $arguments));

            $process = new Process([\PHP_BINARY, 'bin/doom', ...$arguments], $pathname, null, null, 120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw BuildException::becauseStepFailed(
                    'php bin/doom ' . \implode(' ', $arguments),
                    $process->getErrorOutput() . $process->getOutput(),
                );
            }
        }
    }

    private function printSizes(SymfonyStyle $io, GameFolder $folder): void
    {
        $rows = [];
        $total = 0;

        foreach (['app', 'libs', 'resources', 'vendor', 'var/wad'] as $part) {
            $size = $folder->measure($part);
            $total += $size['bytes'];
            $rows[] = [$part, $size['files'], \number_format($size['bytes'] / 1024 / 1024, 2) . ' MB'];
        }

        $rows[] = ['total', '', \number_format($total / 1024 / 1024, 2) . ' MB'];

        $io->table(['Part', 'Files', 'Size'], $rows);
    }
}
