<?php

declare(strict_types=1);

namespace Serafim\Doom\Command\Input;

/**
 * Whether the game lights the level with ray queries.
 */
enum RayMode
{
    /**
     * Rays when the GPU supports them, the plain light otherwise
     */
    case AUTO;

    /**
     * Rays, the game does not start on a GPU without them
     */
    case REQUIRED;

    /**
     * The plain light of the sectors only
     */
    case OFF;
}
