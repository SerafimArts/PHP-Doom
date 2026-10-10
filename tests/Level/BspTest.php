<?php

declare(strict_types=1);

namespace Serafim\Doom\Tests\Level;

use Local\WAD\Map\BoundingBox;
use Local\WAD\Map\Node;
use Local\WAD\Map\NodeChild;
use PHPUnit\Framework\Attributes\CoversClass;
use Serafim\Doom\Level\Bsp;
use Serafim\Doom\Tests\TestCase;

#[CoversClass(Bsp::class)]
final class BspTest extends TestCase
{
    public function testPointsOnTheRightOfTheNodeLineGoToTheRightChild(): void
    {
        $bsp = new Bsp([$this->createNode()], 2);

        // The line runs upwards from the origin, so its right side is x > 0.
        self::assertSame(0, $bsp->findSubsector(10, 5));
        self::assertSame(1, $bsp->findSubsector(-10, 5));
    }

    public function testPointsOnTheLineBelongToTheLeftChild(): void
    {
        $bsp = new Bsp([$this->createNode()], 2);

        self::assertSame(1, $bsp->findSubsector(0, 7));
    }

    public function testPathsListTheNodesAndTheSidesOfTheWayToEverySubsector(): void
    {
        $paths = new Bsp([$this->createNode()], 2)->getPaths();

        self::assertCount(2, $paths);
        self::assertTrue($paths[0][0]['isRight']);
        self::assertFalse($paths[1][0]['isRight']);
    }

    private function createNode(): Node
    {
        $box = new BoundingBox(0, 0, 0, 0);

        return new Node(0, 0, 0, 1, $box, $box, new NodeChild(true, 0), new NodeChild(true, 1));
    }
}
