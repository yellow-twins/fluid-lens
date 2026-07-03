<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Detector;

/**
 * A disjoint-set (union-find) structure used to group structures into clusters
 * of transitively-similar variants.
 */
final class UnionFind
{
    /**
     * @var list<int>
     */
    private array $parent;

    public function __construct(int $size)
    {
        $this->parent = range(0, max(0, $size - 1));
    }

    public function union(int $a, int $b): void
    {
        $this->parent[$this->find($a)] = $this->find($b);
    }

    public function find(int $node): int
    {
        while ($this->parent[$node] !== $node) {
            $this->parent[$node] = $this->parent[$this->parent[$node]];
            $node = $this->parent[$node];
        }

        return $node;
    }

    /**
     * @return array<int, list<int>> the members of each cluster, keyed by its root
     */
    public function clusters(): array
    {
        $clusters = [];
        foreach (array_keys($this->parent) as $node) {
            $clusters[$this->find($node)][] = $node;
        }

        return $clusters;
    }
}
