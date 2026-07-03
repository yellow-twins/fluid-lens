<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Detector;

/**
 * A group of distinct-but-similar structures that could be unified into a single
 * Partial, with the differences passed as arguments.
 */
final class NearDuplicateCluster
{
    /**
     * @param non-empty-list<NearDuplicateMember> $members
     * @param float                               $similarity average pairwise similarity, 0..1
     */
    public function __construct(
        public readonly array $members,
        public readonly float $similarity,
    ) {
    }

    public function memberCount(): int
    {
        return count($this->members);
    }

    public function totalOccurrences(): int
    {
        $total = 0;
        foreach ($this->members as $member) {
            $total += $member->occurrenceCount();
        }

        return $total;
    }
}
