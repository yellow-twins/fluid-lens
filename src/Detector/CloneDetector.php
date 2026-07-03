<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Detector;

use YellowTwins\FluidLens\Fingerprint\SkeletonHasher;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Finds structurally identical markup repeated across templates.
 *
 * Every element subtree is fingerprinted; subtrees that share a fingerprint and
 * occur at least {@see $minOccurrences} times, while being at least
 * {@see $minElements} elements large, form a clone group. Groups fully contained
 * within a larger reported group are suppressed, so a big duplicated block is
 * reported once instead of also reporting each of its inner blocks.
 */
final class CloneDetector
{
    private readonly SkeletonHasher $hasher;

    public function __construct(
        private readonly int $minElements = 3,
        private readonly int $minOccurrences = 2,
        ?SkeletonHasher $hasher = null,
    ) {
        $this->hasher = $hasher ?? new SkeletonHasher();
    }

    /**
     * @param iterable<ParsedTemplate> $templates
     *
     * @return list<CloneGroup>
     */
    public function detect(iterable $templates): array
    {
        $buckets = $this->collectBuckets($templates);
        $groups = $this->buildGroups($buckets);

        return $this->suppressSubsumedGroups($groups);
    }

    /**
     * @param iterable<ParsedTemplate> $templates
     *
     * @return array<string, array{elements: int, occurrences: non-empty-list<CloneOccurrence>}>
     */
    private function collectBuckets(iterable $templates): array
    {
        $buckets = [];
        foreach ($templates as $template) {
            $this->collect($template->file, $template->tree, $buckets);
        }

        return $buckets;
    }

    /**
     * @param array<string, array{elements: int, occurrences: non-empty-list<CloneOccurrence>}> $buckets
     */
    private function collect(string $file, Node $node, array &$buckets): void
    {
        if ($node->isElement()) {
            $skeleton = $this->hasher->fingerprint($node);
            $occurrence = new CloneOccurrence($file, $node->sourceRange?->startLine ?? 0, $node);

            if (isset($buckets[$skeleton->hash])) {
                $buckets[$skeleton->hash]['occurrences'][] = $occurrence;
            } else {
                $buckets[$skeleton->hash] = ['elements' => $skeleton->elementCount, 'occurrences' => [$occurrence]];
            }
        }

        foreach ($node->children() as $child) {
            $this->collect($file, $child, $buckets);
        }
    }

    /**
     * @param array<string, array{elements: int, occurrences: non-empty-list<CloneOccurrence>}> $buckets
     *
     * @return list<CloneGroup>
     */
    private function buildGroups(array $buckets): array
    {
        $groups = [];
        foreach ($buckets as $hash => $bucket) {
            if ($bucket['elements'] < $this->minElements || count($bucket['occurrences']) < $this->minOccurrences) {
                continue;
            }

            $groups[] = new CloneGroup($hash, $bucket['elements'], $bucket['occurrences']);
        }

        return $groups;
    }

    /**
     * @param list<CloneGroup> $groups
     *
     * @return list<CloneGroup>
     */
    private function suppressSubsumedGroups(array $groups): array
    {
        usort(
            $groups,
            static fn (CloneGroup $a, CloneGroup $b): int
                => [$b->elementCount, $b->occurrenceCount()] <=> [$a->elementCount, $a->occurrenceCount()],
        );

        /** @var array<int, true> $covered */
        $covered = [];
        $kept = [];
        foreach ($groups as $group) {
            if ($this->isFullyCovered($group, $covered)) {
                continue;
            }

            $kept[] = $group;
            foreach ($group->occurrences as $occurrence) {
                $this->markCovered($occurrence->node, $covered);
            }
        }

        return $kept;
    }

    /**
     * @param array<int, true> $covered
     */
    private function isFullyCovered(CloneGroup $group, array $covered): bool
    {
        foreach ($group->occurrences as $occurrence) {
            if (!isset($covered[spl_object_id($occurrence->node)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, true> $covered
     */
    private function markCovered(Node $node, array &$covered): void
    {
        $covered[spl_object_id($node)] = true;
        foreach ($node->children() as $child) {
            $this->markCovered($child, $covered);
        }
    }
}
