<?php

declare(strict_types=1);

namespace Serafim\Doom\Level;

use Local\WAD\Map\Node;

/**
 * The binary space partition of a level: a tree whose nodes split the plane by a line and whose leaves are subsectors.
 */
final readonly class Bsp
{
    public function __construct(
        /**
         * The root is the last node
         *
         * @var list<Node>
         */
        private array $nodes,
        /** @var int<0, max> */
        private int $subsectors,
    ) {}

    /**
     * Tells which side of the node line a point is on. Points on the line belong to the left side.
     */
    public static function isRight(Node $node, int $x, int $y): bool
    {
        return $node->dx * ($y - $node->y) - $node->dy * ($x - $node->x) < 0;
    }

    /**
     * @return int<0, max>
     */
    public function findSubsector(int $x, int $y): int
    {
        if ($this->nodes === []) {
            return 0;
        }

        $node = $this->nodes[\count($this->nodes) - 1];

        while (true) {
            $child = $node->leftChild;

            if (self::isRight($node, $x, $y)) {
                $child = $node->rightChild;
            }

            if ($child->isSubsector) {
                \assert($child->index >= 0);

                return $child->index;
            }

            $node = $this->nodes[$child->index];
        }
    }

    /**
     * The nodes on the way from the root to every subsector, with the side of each node that the way takes.
     *
     * @return list<list<array{node: Node, isRight: bool}>> by the index of a subsector
     */
    public function getPaths(): array
    {
        $paths = \array_fill(0, $this->subsectors, []);

        if ($this->nodes !== []) {
            $this->walk(\count($this->nodes) - 1, [], $paths);
        }

        return $paths;
    }

    /**
     * @param list<array{node: Node, isRight: bool}> $path
     * @param list<list<array{node: Node, isRight: bool}>> $paths
     */
    private function walk(int $index, array $path, array &$paths): void
    {
        $node = $this->nodes[$index];

        foreach ([[true, $node->rightChild], [false, $node->leftChild]] as [$isRight, $child]) {
            $next = [...$path, ['node' => $node, 'isRight' => $isRight]];

            if ($child->isSubsector) {
                $paths[$child->index] = $next;

                continue;
            }

            $this->walk($child->index, $next, $paths);
        }
    }
}
