<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Download;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The reference material of the project: the sources are only read and compared with, never copied.
 */
final readonly class ReferenceCatalog
{
    private const string WAD_MD5 = 'f0cefca49926d00903cf57551d901abe';

    private function __construct() {}

    /**
     * @param non-empty-string $directory the `var` directory of the project
     *
     * @return list<DownloadInterface>
     */
    public static function create(string $directory, HttpClientInterface $client, Filesystem $filesystem): array
    {
        return [
            new GitRepository(
                name: 'src/linuxdoom',
                url: 'https://github.com/id-Software/DOOM.git',
                directory: $directory . '/src/linuxdoom',
            ),
            new GitRepository(
                name: 'src/chocolate-doom',
                url: 'https://github.com/chocolate-doom/chocolate-doom.git',
                directory: $directory . '/src/chocolate-doom',
            ),
            new ZipDownload(
                client: $client,
                filesystem: $filesystem,
                name: 'docs/uds',
                url: 'https://www.gamers.org/pub/idgames/docs/editing/dmspec16.zip',
                directory: $directory . '/docs/uds',
                marker: 'uds',
            ),
            new HttpFile(
                client: $client,
                filesystem: $filesystem,
                name: 'wad/doom1.wad',
                url: 'https://github.com/Akbar30Bill/DOOM_wads/raw/master/doom1.wad',
                pathname: $directory . '/wad/doom1.wad',
                md5: self::WAD_MD5,
            ),
        ];
    }
}
