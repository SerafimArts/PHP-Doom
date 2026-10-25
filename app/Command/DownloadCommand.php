<?php

declare(strict_types=1);

namespace Serafim\Doom\Command;

use Serafim\Doom\Command\Download\DownloadInterface;
use Serafim\Doom\Command\Download\ReferenceCatalog;
use Serafim\Doom\Exception\DownloadException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(name: 'download', description: 'Download the reference sources, the specs and doom1.wad into var/')]
final class DownloadCommand extends Command
{
    /** @var list<DownloadInterface> */
    private readonly array $downloads;

    /**
     * @param list<DownloadInterface>|null $downloads {@see null} means the {@see ReferenceCatalog}
     */
    public function __construct(?array $downloads = null)
    {
        parent::__construct();

        $this->downloads = $downloads ?? ReferenceCatalog::create(
            \dirname(__DIR__, 2) . '/var',
            HttpClient::create(),
            new Filesystem(),
        );
    }

    /**
     * @throws DownloadException in case of the material cannot be downloaded or verified
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->downloads as $download) {
            if ($download->isDownloaded()) {
                $io->writeln(\sprintf('<comment>skip</comment>  %s', $download->getName()));

                continue;
            }

            $io->writeln(\sprintf('<info>fetch</info> %s', $download->getName()));
            $download->download();
        }

        $io->success('The reference material is in place.');

        return self::SUCCESS;
    }
}
