<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Detector;

use YellowTwins\FluidLens\Fingerprint\PqGramProfile;
use YellowTwins\FluidLens\Fingerprint\PqGramProfiler;
use YellowTwins\FluidLens\Fingerprint\SkeletonHasher;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Finds groups of distinct-but-similar structures across templates using pq-gram
 * similarity — the near-duplicates that exact clone detection misses.
 *
 * Identical structures are first collapsed into a single variant (that is exact
 * clone detection's job). The remaining distinct structures are compared pairwise
 * within a size band; pairs at or above {@see $threshold} similarity are linked,
 * and transitively linked variants form a cluster worth unifying into one Partial.
 */
final class NearDuplicateDetector
{
    /**
     * A child whose element count is at least this fraction of its parent's is
     * essentially the same physical block, not a distinct variant, so it is not
     * collected on its own. This prevents a block and its own near-total inner
     * blocks from being reported as similar to each other.
     */
    private const NEAR_TOTAL_RATIO = 0.9;

    private readonly SkeletonHasher $hasher;
    private readonly PqGramProfiler $profiler;

    public function __construct(
        private readonly float $threshold = 0.8,
        private readonly int $minElements = 4,
        ?SkeletonHasher $hasher = null,
        ?PqGramProfiler $profiler = null,
    ) {
        $this->hasher = $hasher ?? new SkeletonHasher();
        $this->profiler = $profiler ?? new PqGramProfiler();
    }

    /**
     * @param iterable<ParsedTemplate> $templates
     *
     * @return list<NearDuplicateCluster>
     */
    public function detect(iterable $templates): array
    {
        $members = $this->collectDistinctStructures($templates);
        if (count($members) < 2) {
            return [];
        }

        $profiles = array_map(
            fn (NearDuplicateMember $member): PqGramProfile => $this->profiler->profile($member->representative),
            $members,
        );

        return $this->buildClusters($members, $profiles);
    }

    /**
     * @param iterable<ParsedTemplate> $templates
     *
     * @return list<NearDuplicateMember>
     */
    private function collectDistinctStructures(iterable $templates): array
    {
        /** @var array<string, array{node: Node, elements: int, occurrences: non-empty-list<CloneOccurrence>}> $byHash */
        $byHash = [];
        foreach ($templates as $template) {
            $this->collect($template->file, $template->tree, 0, $byHash);
        }

        return array_values(array_map(
            static fn (array $bucket): NearDuplicateMember
                => new NearDuplicateMember($bucket['node'], $bucket['elements'], $bucket['occurrences']),
            $byHash,
        ));
    }

    /**
     * @param array<string, array{node: Node, elements: int, occurrences: non-empty-list<CloneOccurrence>}> $byHash
     */
    private function collect(string $file, Node $node, int $parentElements, array &$byHash): void
    {
        $elementCount = 0;

        if ($node->isElement()) {
            $skeleton = $this->hasher->fingerprint($node);
            $elementCount = $skeleton->elementCount;

            if ($this->isCandidate($elementCount, $parentElements)) {
                $this->register($byHash, $skeleton->hash, $node, $elementCount, $file);
            }
        }

        foreach ($node->children() as $child) {
            $this->collect($file, $child, $elementCount, $byHash);
        }
    }

    private function isCandidate(int $elementCount, int $parentElements): bool
    {
        if ($elementCount < $this->minElements) {
            return false;
        }

        return $parentElements === 0 || $elementCount <= self::NEAR_TOTAL_RATIO * $parentElements;
    }

    /**
     * @param array<string, array{node: Node, elements: int, occurrences: non-empty-list<CloneOccurrence>}> $byHash
     */
    private function register(array &$byHash, string $hash, Node $node, int $elementCount, string $file): void
    {
        $occurrence = new CloneOccurrence($file, $node->sourceRange?->startLine ?? 0, $node);

        if (isset($byHash[$hash])) {
            $byHash[$hash]['occurrences'][] = $occurrence;
        } else {
            $byHash[$hash] = ['node' => $node, 'elements' => $elementCount, 'occurrences' => [$occurrence]];
        }
    }

    /**
     * @param list<NearDuplicateMember> $members
     * @param list<PqGramProfile>       $profiles
     *
     * @return list<NearDuplicateCluster>
     */
    private function buildClusters(array $members, array $profiles): array
    {
        $count = count($members);
        $unionFind = new UnionFind($count);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (!$this->withinSizeBand($members[$i]->elementCount, $members[$j]->elementCount)) {
                    continue;
                }

                $similarity = $this->profiler->similarity($profiles[$i], $profiles[$j]);
                if ($similarity >= $this->threshold && $similarity < 1.0) {
                    $unionFind->union($i, $j);
                }
            }
        }

        $clusters = [];
        foreach ($unionFind->clusters() as $indices) {
            if (count($indices) < 2) {
                continue;
            }

            $clusters[] = new NearDuplicateCluster(
                array_values(array_map(static fn (int $index): NearDuplicateMember => $members[$index], $indices)),
                $this->averageSimilarity($indices, $profiles),
            );
        }

        usort(
            $clusters,
            static fn (NearDuplicateCluster $a, NearDuplicateCluster $b): int
                => [$b->totalOccurrences(), $b->memberCount()] <=> [$a->totalOccurrences(), $a->memberCount()],
        );

        return $clusters;
    }

    /**
     * @param list<int>           $indices
     * @param list<PqGramProfile> $profiles
     */
    private function averageSimilarity(array $indices, array $profiles): float
    {
        $sum = 0.0;
        $pairs = 0;
        foreach ($indices as $a) {
            foreach ($indices as $b) {
                if ($a < $b) {
                    $sum += $this->profiler->similarity($profiles[$a], $profiles[$b]);
                    $pairs++;
                }
            }
        }

        return $pairs === 0 ? 0.0 : $sum / $pairs;
    }

    private function withinSizeBand(int $a, int $b): bool
    {
        return max($a, $b) <= min($a, $b) * 2;
    }
}
