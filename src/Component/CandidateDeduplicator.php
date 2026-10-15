<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Parser\Node;

/**
 * Keeps one suggestion per piece of markup.
 *
 * The signals overlap: a Partial's whole body is also found as copied markup,
 * and a copied block may sit inside a larger copied block. Candidates are taken
 * best first; one whose every occurrence lies inside markup an already kept
 * candidate covers adds nothing and is dropped.
 */
final class CandidateDeduplicator
{
    /** @var list<array{candidate: ComponentCandidate, roots: list<Node>}> */
    private array $entries = [];

    /**
     * @param list<Node> $roots the markup each occurrence of the candidate stands for
     */
    public function add(ComponentCandidate $candidate, array $roots): void
    {
        $this->entries[] = ['candidate' => $candidate, 'roots' => $roots];
    }

    /**
     * @return array{0: list<ComponentCandidate>, 1: int} the kept candidates, best first,
     *                                                    and how many were dropped
     */
    public function result(): array
    {
        $entries = $this->entries;
        usort(
            $entries,
            static fn (array $a, array $b): int => [$b['candidate']->score->value, $a['candidate']->name]
                <=> [$a['candidate']->score->value, $b['candidate']->name],
        );

        /** @var array<int, true> $covered */
        $covered = [];
        $kept = [];
        $dropped = 0;
        foreach ($entries as $entry) {
            if ($this->isCovered($entry['roots'], $covered)) {
                $dropped++;
                continue;
            }

            $kept[] = $entry['candidate'];
            foreach ($entry['roots'] as $root) {
                $this->cover($root, $covered);
            }
        }

        return [$kept, $dropped];
    }

    /**
     * @param list<Node>       $roots
     * @param array<int, true> $covered
     */
    private function isCovered(array $roots, array $covered): bool
    {
        if ($roots === []) {
            return false;
        }

        foreach ($roots as $root) {
            if (!isset($covered[spl_object_id($root)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, true> $covered
     */
    private function cover(Node $node, array &$covered): void
    {
        $covered[spl_object_id($node)] = true;
        foreach ($node->children() as $child) {
            $this->cover($child, $covered);
        }
    }
}
