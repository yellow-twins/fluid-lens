<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Parser;

/**
 * A single node in a parsed Fluid template tree.
 *
 * The tree is intentionally lightweight and framework-agnostic: it models only
 * what structural analysis needs (tag name, attributes, children, text and the
 * source location) and nothing of PHP's DOM extension leaks past the parser.
 */
final class Node
{
    /** @var list<Node> */
    private array $children = [];

    private ?Node $parent = null;

    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly NodeType $type,
        public readonly string $name = '',
        public readonly array $attributes = [],
        public readonly string $text = '',
        public readonly ?SourceRange $sourceRange = null,
    ) {
    }

    public function addChild(Node $child): void
    {
        $child->parent = $this;
        $this->children[] = $child;
    }

    /**
     * @return list<Node>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * The element children of this node, skipping text and comment nodes.
     *
     * @return list<Node>
     */
    public function elementChildren(): array
    {
        return array_values(
            array_filter($this->children, static fn (Node $child): bool => $child->isElement()),
        );
    }

    public function parent(): ?Node
    {
        return $this->parent;
    }

    public function isElement(): bool
    {
        return $this->type === NodeType::Element;
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }
}
