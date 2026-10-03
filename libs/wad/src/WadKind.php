<?php

declare(strict_types=1);

namespace Local\WAD;

enum WadKind: string
{
    /** The main data file of a game */
    case IWAD = 'IWAD';

    /** The patch file that adds or replaces lumps */
    case PWAD = 'PWAD';
}
