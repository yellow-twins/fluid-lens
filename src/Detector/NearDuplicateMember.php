<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Detector;

use YellowTwins\FluidLens\Parser\Node;

/**
 * One structural variant within a near-duplicate cluster: a distinct structure,
 * how large it is and everywhere it occurs.
 */
final class NearDuplicateMember
{
    /**
     * @param non-empty-list<CloneOccurrence> $occurrences
     */
    public function __construct(
        public readonly Node $representative,
        public readonly int $elementCount,
        public readonly array $occurrences,
    ) {
    }

    public function occurrenceCount(): int
    {
        return count($this->occurrences);
    }
}
