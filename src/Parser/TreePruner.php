<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Parser;

/**
 * Produces a copy of a node tree with the elements on suppressed source lines
 * removed, together with everything nested inside them.
 *
 * Detection then runs on the pruned tree, so an {@see \YellowTwins\FluidLens\Template\SuppressionScanner}
 * marker excludes its block from every analysis without the detectors needing to
 * know suppression exists.
 */
final class TreePruner
{
    /**
     * @param array<int, true> $ignoredLines
     */
    public function prune(Node $node, array $ignoredLines): Node
    {
        $copy = new Node($node->type, $node->name, $node->attributes, $node->text, $node->sourceRange);

        foreach ($node->children() as $child) {
            if ($this->isSuppressed($child, $ignoredLines)) {
                continue;
            }

            $copy->addChild($this->prune($child, $ignoredLines));
        }

        return $copy;
    }

    /**
     * @param array<int, true> $ignoredLines
     */
    private function isSuppressed(Node $node, array $ignoredLines): bool
    {
        $line = $node->sourceRange?->startLine;

        return $node->isElement() && $line !== null && isset($ignoredLines[$line]);
    }
}
