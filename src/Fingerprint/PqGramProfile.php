<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fingerprint;

/**
 * The pq-gram profile of a subtree: the bag (multiset) of its pq-grams, keyed by
 * gram with its multiplicity, together with the total number of grams.
 *
 * Comparing two profiles approximates the tree edit distance between the two
 * subtrees, which lets near-duplicate structures be detected with a score.
 */
final class PqGramProfile
{
    /**
     * @param array<string, int> $grams
     */
    public function __construct(
        public readonly array $grams,
        public readonly int $total,
    ) {
    }
}
