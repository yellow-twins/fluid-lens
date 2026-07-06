<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Base for consistency checks that recognise competing *Fluid* conventions —
 * tag versus inline syntax, ViewHelper versus raw markup — rather than CSS
 * libraries. A subclass returns the variant labels it finds in one template's
 * tree; this base aggregates them across the project exactly like
 * {@see SignatureCheck} does, so more than one variant means the project mixes
 * conventions.
 */
abstract class FluidUsageCheck implements ConsistencyCheck
{
    /**
     * The variant labels present in this template (empty if none apply).
     *
     * @return list<string>
     */
    abstract protected function variantsIn(Node $tree): array;

    public function analyze(array $templates): ConsistencyResult
    {
        /** @var array<string, array<string, true>> $byLabel */
        $byLabel = [];
        foreach ($templates as $template) {
            foreach ($this->variantsIn($template->tree) as $label) {
                $byLabel[$label][$template->file] = true;
            }
        }

        $usages = [];
        foreach ($byLabel as $label => $files) {
            $usages[] = new Usage($label, array_keys($files));
        }

        usort($usages, static fn (Usage $a, Usage $b): int => $b->fileCount() <=> $a->fileCount());

        return new ConsistencyResult($this->name(), $this->title(), $usages);
    }
}
