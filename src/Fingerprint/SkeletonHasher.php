<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fingerprint;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

/**
 * Builds a canonical, structural fingerprint of a Fluid subtree.
 *
 * The canonical form keeps what defines the *shape* of the markup — element tag
 * names, nesting and the set of (non-presentational) attribute names — and drops
 * everything interchangeable: attribute values, text and variable content. As a
 * result two blocks that differ only in their classes or their content produce
 * the same fingerprint and are recognised as the same structure, which is exactly
 * the duplication that should be extracted into a Partial.
 */
final class SkeletonHasher
{
    /**
     * Attribute names that never influence structure: they are presentational or
     * pure Fluid bookkeeping and are typically passed to a Partial as arguments.
     *
     * @var list<string>
     */
    private const IGNORED_ATTRIBUTES = ['class', 'id', 'style', 'data-namespace-typo3-fluid'];

    private const TEXT_MARKER = '#t';

    public function fingerprint(Node $node): Skeleton
    {
        return new Skeleton(sha1($this->canonical($node)), $this->countElements($node));
    }

    public function canonical(Node $node): string
    {
        return match ($node->type) {
            NodeType::Element => $this->elementCanonical($node),
            NodeType::Text => self::TEXT_MARKER,
            NodeType::Root, NodeType::Comment => $this->childrenCanonical($node),
        };
    }

    /**
     * The structural signature of a single element — its tag name and the set of
     * non-presentational attribute names — without its children. Shared with the
     * pq-gram profiler so both stages judge structure by the same rules.
     */
    public function nodeSignature(Node $node): string
    {
        return sprintf('%s[%s]', $node->name, $this->attributeSignature($node));
    }

    private function elementCanonical(Node $node): string
    {
        return sprintf('%s(%s)', $this->nodeSignature($node), $this->childrenCanonical($node));
    }

    private function childrenCanonical(Node $node): string
    {
        $canonical = '';
        foreach ($node->children() as $child) {
            if ($child->type === NodeType::Comment) {
                continue;
            }
            $canonical .= $this->canonical($child);
        }

        return $canonical;
    }

    private function attributeSignature(Node $node): string
    {
        $keys = array_values(array_diff(array_keys($node->attributes), self::IGNORED_ATTRIBUTES));
        sort($keys);

        return implode(',', $keys);
    }

    private function countElements(Node $node): int
    {
        $count = $node->isElement() ? 1 : 0;
        foreach ($node->children() as $child) {
            $count += $this->countElements($child);
        }

        return $count;
    }
}
