<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Draft;

use YellowTwins\FluidLens\Parser\Node;

/**
 * The edits that turn concrete markup into a component body: argument
 * placeholders for values that differ, a slot for content that differs too much,
 * a condition around optional elements, renamed variables, dropped nodes.
 *
 * Nodes are addressed by identity, so the edits apply to exactly the copy the
 * draft is written from.
 */
final class Substitutions
{
    /** @var array<int, array<string, string>> */
    private array $attributes = [];

    /** @var array<int, string> */
    private array $texts = [];

    /** @var array<int, string> */
    private array $replacements = [];

    /** @var array<int, string> */
    private array $conditions = [];

    /** @var array<int, true> */
    private array $dropped = [];

    /** @var array<string, string> variable path => replacement path */
    private array $renames = [];

    public function setAttribute(Node $element, string $attribute, string $value): void
    {
        $this->attributes[spl_object_id($element)][$attribute] = $value;
    }

    public function setText(Node $text, string $value): void
    {
        $this->texts[spl_object_id($text)] = $value;
    }

    /**
     * Replaces a node, children included, with raw markup such as {@code <f:slot />}.
     */
    public function replace(Node $node, string $markup): void
    {
        $this->replacements[spl_object_id($node)] = $markup;
    }

    /**
     * Renders the element only when the condition holds.
     */
    public function wrapInCondition(Node $element, string $condition): void
    {
        $this->conditions[spl_object_id($element)] = $condition;
    }

    public function drop(Node $node): void
    {
        $this->dropped[spl_object_id($node)] = true;
    }

    /**
     * Renames a variable path in every expression, e.g. settings.listPageUid => listPageUid.
     */
    public function renameVariable(string $path, string $replacement): void
    {
        $this->renames[$path] = $replacement;
    }

    public function attributeOverride(Node $element, string $attribute): ?string
    {
        return $this->attributes[spl_object_id($element)][$attribute] ?? null;
    }

    public function textOverride(Node $text): ?string
    {
        return $this->texts[spl_object_id($text)] ?? null;
    }

    public function replacement(Node $node): ?string
    {
        return $this->replacements[spl_object_id($node)] ?? null;
    }

    public function condition(Node $element): ?string
    {
        return $this->conditions[spl_object_id($element)] ?? null;
    }

    public function isDropped(Node $node): bool
    {
        return isset($this->dropped[spl_object_id($node)]);
    }

    /**
     * Applies the variable renames to a text or attribute value. Longer paths go
     * first, so {@code settings.a.b} is not caught by the rename of {@code settings.a}.
     */
    public function applyRenames(string $value): string
    {
        if ($this->renames === [] || !str_contains($value, '{')) {
            return $value;
        }

        $renames = $this->renames;
        uksort($renames, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($renames as $path => $replacement) {
            $pattern = '/(?<![\w.$])' . preg_quote($path, '/') . '(?![\w-])/';
            $value = (string) preg_replace($pattern, $replacement, $value);
        }

        return $value;
    }
}
