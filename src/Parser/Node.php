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
     * Where whitespace stood in the source between children. The parser drops
     * whitespace-only text, which is right for structural analysis; writing
     * markup back needs to know where it was, because between inline elements
     * it renders as a space.
     *
     * @var array<int, true> child object id => whitespace preceded it
     */
    private array $whitespaceBefore = [];

    private bool $pendingWhitespace = false;

    private bool $trailingWhitespace = false;

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

        if ($this->pendingWhitespace) {
            $this->whitespaceBefore[spl_object_id($child)] = true;
            $this->pendingWhitespace = false;
        }
    }

    /**
     * Records dropped whitespace before the next child, or at the end when no
     * child follows.
     */
    public function addWhitespace(): void
    {
        $this->pendingWhitespace = true;
        $this->trailingWhitespace = true;
    }

    /**
     * Ends the children: whitespace recorded after the last one is trailing.
     */
    public function closeChildren(): void
    {
        $this->trailingWhitespace = $this->pendingWhitespace;
        $this->pendingWhitespace = false;
    }

    public function hasWhitespaceBefore(Node $child): bool
    {
        return isset($this->whitespaceBefore[spl_object_id($child)]);
    }

    public function hasTrailingWhitespace(): bool
    {
        return $this->trailingWhitespace;
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
