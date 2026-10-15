<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Draft;

use YellowTwins\FluidLens\Fluid\SourceCasing;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

/**
 * Writes a parsed subtree back as readable Fluid markup, applying
 * {@see Substitutions} on the way.
 *
 * Only block-level elements are broken onto lines of their own; inline content
 * (text and inline elements) is kept together on one line exactly as it was, so
 * the draft adds no whitespace where it would change the output. Whitespace-only
 * text is not preserved by the parser, so a draft is a starting point to review,
 * not a byte-exact copy.
 */
final class FluidMarkupWriter
{
    private const INDENT = '    ';

    private const MAX_LINE = 110;

    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr',
    ];

    private const BLOCK_ELEMENTS = [
        'address', 'article', 'aside', 'blockquote', 'details', 'dialog', 'div', 'dl', 'fieldset', 'figure',
        'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main', 'nav', 'ol', 'p',
        'picture', 'section', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
        'f:if', 'f:then', 'f:else', 'f:for', 'f:switch', 'f:case', 'f:defaultcase', 'f:section', 'f:groupedfor',
    ];

    /**
     * @param list<Node> $nodes
     */
    public function write(array $nodes, Substitutions $substitutions, SourceCasing $casing): string
    {
        $lines = [];
        foreach ($nodes as $node) {
            array_push($lines, ...$this->lines($node, 0, $substitutions, $casing));
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function lines(Node $node, int $depth, Substitutions $substitutions, SourceCasing $casing): array
    {
        if ($substitutions->isDropped($node) || $node->type === NodeType::Comment) {
            return [];
        }

        $indent = str_repeat(self::INDENT, $depth);

        $replacement = $substitutions->replacement($node);
        if ($replacement !== null) {
            return [$indent . $replacement];
        }

        if ($node->type === NodeType::Text) {
            $text = $this->text($node, $substitutions);

            return trim($text) === '' ? [] : [$indent . trim($text)];
        }

        if (!$node->isElement()) {
            return [];
        }

        $condition = $substitutions->condition($node);
        if ($condition !== null) {
            $inner = $this->elementLines($node, $depth + 1, $substitutions, $casing);

            return [
                $indent . sprintf('<f:if condition="%s">', $this->escape($condition)),
                ...$inner,
                $indent . '</f:if>',
            ];
        }

        return $this->elementLines($node, $depth, $substitutions, $casing);
    }

    /**
     * @return list<string>
     */
    private function elementLines(Node $node, int $depth, Substitutions $substitutions, SourceCasing $casing): array
    {
        $indent = str_repeat(self::INDENT, $depth);
        $compact = $this->compactElement($node, $substitutions, $casing);

        $hasBlocks = $this->hasBlockDescendant($node, $substitutions);
        $isBlock = in_array($node->name, self::BLOCK_ELEMENTS, true);
        if (!$hasBlocks && (!$isBlock || strlen($indent . $compact) <= self::MAX_LINE)) {
            return [$indent . $compact];
        }

        $name = $casing->restoreTag($node->name);
        $lines = [$indent . '<' . $name . $this->attributes($node, $substitutions, $casing) . '>'];
        $inline = '';
        foreach ($node->children() as $child) {
            if (!$this->isBlockNode($child, $substitutions)) {
                $inline .= $this->compact($child, $substitutions, $casing);
                continue;
            }

            $lines = [...$lines, ...$this->inlineLine($inline, $depth + 1)];
            $inline = '';
            array_push($lines, ...$this->lines($child, $depth + 1, $substitutions, $casing));
        }
        $lines = [...$lines, ...$this->inlineLine($inline, $depth + 1)];
        $lines[] = $indent . '</' . $name . '>';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function inlineLine(string $inline, int $depth): array
    {
        return trim($inline) === '' ? [] : [str_repeat(self::INDENT, $depth) . trim($inline)];
    }

    /**
     * A child that gets a line of its own: a block element, an element holding
     * one, or an element wrapped in a condition.
     */
    private function isBlockNode(Node $node, Substitutions $substitutions): bool
    {
        if (!$node->isElement() || $substitutions->isDropped($node) || $substitutions->replacement($node) !== null) {
            return false;
        }

        return in_array($node->name, self::BLOCK_ELEMENTS, true)
            || $substitutions->condition($node) !== null
            || $this->hasBlockDescendant($node, $substitutions);
    }

    private function compact(Node $node, Substitutions $substitutions, SourceCasing $casing): string
    {
        if ($substitutions->isDropped($node) || $node->type === NodeType::Comment) {
            return '';
        }

        $replacement = $substitutions->replacement($node);
        if ($replacement !== null) {
            return $replacement;
        }

        if ($node->type === NodeType::Text) {
            return (string) preg_replace('/\s+/', ' ', $this->text($node, $substitutions));
        }

        $markup = $this->compactElement($node, $substitutions, $casing);
        $condition = $substitutions->condition($node);

        return $condition === null
            ? $markup
            : sprintf('<f:if condition="%s">%s</f:if>', $this->escape($condition), $markup);
    }

    /**
     * The element on one line, without a condition wrapped around it.
     */
    private function compactElement(Node $node, Substitutions $substitutions, SourceCasing $casing): string
    {
        $name = $casing->restoreTag($node->name);
        $attributes = $this->attributes($node, $substitutions, $casing);

        $children = '';
        foreach ($node->children() as $child) {
            $children .= $this->compact($child, $substitutions, $casing);
        }

        return match (true) {
            in_array($node->name, self::VOID_ELEMENTS, true) => '<' . $name . $attributes . ' />',
            $children === '' && str_contains($node->name, ':') => '<' . $name . $attributes . ' />',
            default => '<' . $name . $attributes . '>' . $children . '</' . $name . '>',
        };
    }

    private function attributes(Node $node, Substitutions $substitutions, SourceCasing $casing): string
    {
        $rendered = '';
        foreach ($node->attributes as $attribute => $value) {
            $value = $substitutions->attributeOverride($node, $attribute)
                ?? $substitutions->applyRenames($value);
            $rendered .= sprintf(' %s="%s"', $casing->restoreAttribute($attribute), $this->escape($value));
        }

        return $rendered;
    }

    private function text(Node $node, Substitutions $substitutions): string
    {
        $text = $substitutions->textOverride($node) ?? $substitutions->applyRenames($node->text);

        return str_replace("\u{00A0}", '&nbsp;', $text);
    }

    private function hasBlockDescendant(Node $node, Substitutions $substitutions): bool
    {
        foreach ($node->children() as $child) {
            if ($this->isBlockNode($child, $substitutions)) {
                return true;
            }
        }

        return false;
    }

    private function escape(string $value): string
    {
        return str_replace('"', '&quot;', $value);
    }
}
