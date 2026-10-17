<?php

declare(strict_types=1);

namespace Serafim\Doom\Game\Special;

/**
 * Something that changes the level a little every tic: a door that opens, a lift, a flickering light.
 */
interface Thinker
{
    /**
     * Runs one tic.
     *
     * @return bool {@see false} when the thinker is done and has to be removed
     */
    public function think(): bool;
}
