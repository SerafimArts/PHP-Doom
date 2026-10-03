<?php

declare(strict_types=1);

namespace Local\WAD\Map;

/**
 * A child of a BSP node: another node or a subsector.
 */
final readonly class NodeChild
{
    public function __construct(
        /**
         * Whether the child is a subsector (a leaf) and not a node
         */
        public bool $isSubsector,
        /**
         * Index into SSECTORS or NODES depending on the kind
         *
         * @var int<0, 32767>
         */
        public int $index,
    ) {}
}
