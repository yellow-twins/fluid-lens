<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Detector;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Detector\UnionFind;

final class UnionFindTest extends TestCase
{
    public function testTransitivelyLinkedNodesShareACluster(): void
    {
        $unionFind = new UnionFind(5);
        $unionFind->union(0, 1);
        $unionFind->union(1, 2);
        $unionFind->union(3, 4);

        $clusters = array_map('count', $unionFind->clusters());
        sort($clusters);

        self::assertSame([2, 3], $clusters);
    }
}
