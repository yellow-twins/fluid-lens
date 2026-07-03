<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fingerprint;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Builds pq-gram profiles of subtrees and measures their similarity.
 *
 * A pq-gram captures a small, local shape of the tree: a stem of {@code p} nodes
 * (an anchor together with its nearest ancestors) and a base of {@code q}
 * consecutive children. The bag of all pq-grams of a tree — padded with dummy
 * nodes at its borders — is its profile. The overlap of two profiles (a Dice
 * coefficient over the bags) approximates their tree edit distance, so two blocks
 * that differ by only a node or an attribute score highly but below 1.
 *
 * @see https://dl.acm.org/doi/10.1145/1670243.1670247 Augsten et al., "The pq-gram distance"
 */
final class PqGramProfiler
{
    private const DUMMY = '*';

    private readonly SkeletonHasher $hasher;

    public function __construct(
        private readonly int $stemLength = 2,
        private readonly int $baseLength = 3,
        ?SkeletonHasher $hasher = null,
    ) {
        $this->hasher = $hasher ?? new SkeletonHasher();
    }

    public function profile(Node $node): PqGramProfile
    {
        /** @var array<string, int> $grams */
        $grams = [];
        $this->collect($node, array_fill(0, $this->stemLength, self::DUMMY), $grams);

        return new PqGramProfile($grams, array_sum($grams));
    }

    /**
     * The similarity of two profiles in the range 0..1, where 1 means their bags
     * of pq-grams are identical.
     */
    public function similarity(PqGramProfile $a, PqGramProfile $b): float
    {
        if ($a->total === 0 && $b->total === 0) {
            return 1.0;
        }

        $shared = 0;
        foreach ($a->grams as $gram => $count) {
            $shared += min($count, $b->grams[$gram] ?? 0);
        }

        return 2 * $shared / ($a->total + $b->total);
    }

    /**
     * @param list<string>          $stem
     * @param array<string, int>    $grams
     */
    private function collect(Node $node, array $stem, array &$grams): void
    {
        $stem = $this->shift($stem, $this->label($node), $this->stemLength);
        $children = $node->elementChildren();

        if ($children === []) {
            $this->record($grams, $stem, array_fill(0, $this->baseLength, self::DUMMY));

            return;
        }

        $base = array_fill(0, $this->baseLength, self::DUMMY);
        foreach ($children as $child) {
            $base = $this->shift($base, $this->label($child), $this->baseLength);
            $this->record($grams, $stem, $base);
        }

        for ($i = 1; $i < $this->baseLength; $i++) {
            $base = $this->shift($base, self::DUMMY, $this->baseLength);
            $this->record($grams, $stem, $base);
        }

        foreach ($children as $child) {
            $this->collect($child, $stem, $grams);
        }
    }

    /**
     * @param list<string> $register
     *
     * @return list<string>
     */
    private function shift(array $register, string $value, int $size): array
    {
        $register[] = $value;

        return array_slice($register, -$size);
    }

    /**
     * @param array<string, int> $grams
     * @param list<string>       $stem
     * @param list<string>       $base
     */
    private function record(array &$grams, array $stem, array $base): void
    {
        $key = implode('/', $stem) . '|' . implode('/', $base);
        $grams[$key] = ($grams[$key] ?? 0) + 1;
    }

    private function label(Node $node): string
    {
        return $this->hasher->nodeSignature($node);
    }
}
