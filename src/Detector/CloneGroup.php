<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Detector;

use YellowTwins\FluidLens\Parser\Node;

/**
 * A set of structurally identical subtrees found across the analysed templates —
 * a candidate for extraction into a single reusable Partial.
 */
final class CloneGroup
{
    /**
     * @param non-empty-list<CloneOccurrence> $occurrences
     */
    public function __construct(
        public readonly string $hash,
        public readonly int $elementCount,
        public readonly array $occurrences,
    ) {
    }

    public function occurrenceCount(): int
    {
        return count($this->occurrences);
    }

    /**
     * A representative subtree, used to preview the shared structure.
     */
    public function representative(): Node
    {
        return $this->occurrences[0]->node;
    }
}
