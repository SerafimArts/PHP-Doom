<?php

declare(strict_types=1);

namespace Local\WAD\Wad;

/**
 * The lumps of the WAD that the game reads by name.
 */
enum LumpName: string
{
    case PLAYPAL = 'PLAYPAL';

    case COLORMAP = 'COLORMAP';

    case PNAMES = 'PNAMES';

    case TEXTURE1 = 'TEXTURE1';

    case TEXTURE2 = 'TEXTURE2';
}
