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
 * The output renders like the source:
 *
 * - only block-level elements get lines of their own; inline content (text and
 *   inline elements) stays together on one line, with a space exactly where the
 *   source had whitespace;
 * - a tag too long for one line gets its attributes on lines of their own —
 *   whitespace inside a tag never reaches the output.
 */
final class FluidMarkupWriter
{
    private const INDENT = '    ';

    private const MAX_LINE = 110;

    /**
     * In a line that is too long anyway, opening tags longer than this are
     * wrapped attribute by attribute.
     */
    private const MAX_TAG = 80;

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
        $writing = new MarkupWriting($substitutions, $casing);
        $lines = [];
        foreach ($nodes as $node) {
            array_push($lines, ...$this->lines($node, 0, $writing));
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function lines(Node $node, int $depth, MarkupWriting $writing): array
    {
        $substitutions = $writing->substitutions;
        if ($substitutions->isDropped($node) || $node->type === NodeType::Comment) {
            return [];
        }

        $indent = str_repeat(self::INDENT, $depth);

        $replacement = $substitutions->replacement($node);
        if ($replacement !== null) {
            return [$indent . $replacement];
        }

        if ($node->type === NodeType::Text) {
            $text = trim($this->text($node, $writing));

            return $text === '' ? [] : [$indent . $text];
        }

        if (!$node->isElement()) {
            return [];
        }

        $condition = $substitutions->condition($node);
        if ($condition !== null) {
            return [
                $indent . sprintf('<f:if condition="%s">', $this->escape($condition)),
                ...$this->elementLines($node, $depth + 1, $writing),
                $indent . '</f:if>',
            ];
        }

        return $this->elementLines($node, $depth, $writing);
    }

    /**
     * @return list<string>
     */
    private function elementLines(Node $node, int $depth, MarkupWriting $writing): array
    {
        $indent = str_repeat(self::INDENT, $depth);
        $hasBlocks = $this->hasBlockDescendant($node, $writing);
        $isBlock = in_array($node->name, self::BLOCK_ELEMENTS, true);

        if (!$hasBlocks) {
            $compact = $this->compactElement($node, $depth, false, $writing);
            if (strlen($indent . $compact) <= self::MAX_LINE) {
                return [$indent . $compact];
            }

            if (!$isBlock) {
                return [$indent . $this->compactElement($node, $depth, true, $writing)];
            }
        }

        $name = $writing->casing->restoreTag($node->name);
        $opening = $this->openingTag($node, $depth, false, $writing);
        if (strlen($indent . $opening . '>') > self::MAX_LINE) {
            $opening = $this->openingTag($node, $depth, true, $writing);
        }

        $lines = [$indent . $opening . '>'];
        $group = [];
        foreach ($node->children() as $child) {
            if (!$this->isBlockNode($child, $writing)) {
                $group[] = $child;
                continue;
            }

            $lines = [...$lines, ...$this->inlineLines($node, $group, $depth + 1, $writing)];
            $group = [];
            array_push($lines, ...$this->lines($child, $depth + 1, $writing));
        }
        $lines = [...$lines, ...$this->inlineLines($node, $group, $depth + 1, $writing)];
        $lines[] = $indent . '</' . $name . '>';

        return $lines;
    }

    /**
     * A run of inline children on one line, as the source had it; long tags are
     * wrapped only when the line would be too long otherwise.
     *
     * @param list<Node> $group
     *
     * @return list<string>
     */
    private function inlineLines(Node $parent, array $group, int $depth, MarkupWriting $writing): array
    {
        $indent = str_repeat(self::INDENT, $depth);
        $line = trim($this->inlineRun($parent, $group, $depth, false, $writing));
        if ($line === '') {
            return [];
        }

        if (strlen($indent . $line) > self::MAX_LINE) {
            $line = trim($this->inlineRun($parent, $group, $depth, true, $writing));
        }

        return [$indent . $line];
    }

    /**
     * @param list<Node> $group
     */
    private function inlineRun(Node $parent, array $group, int $depth, bool $wrap, MarkupWriting $writing): string
    {
        $run = '';
        foreach ($group as $child) {
            $content = $this->compact($child, $depth, $wrap, $writing);
            if ($content !== '') {
                $run .= ($run !== '' && $parent->hasWhitespaceBefore($child) ? ' ' : '') . $content;
            }
        }

        return $run;
    }

    private function compact(Node $node, int $depth, bool $wrap, MarkupWriting $writing): string
    {
        $substitutions = $writing->substitutions;
        if ($substitutions->isDropped($node) || $node->type === NodeType::Comment) {
            return '';
        }

        $replacement = $substitutions->replacement($node);
        if ($replacement !== null) {
            return $replacement;
        }

        if ($node->type === NodeType::Text) {
            return (string) preg_replace('/\s+/', ' ', $this->text($node, $writing));
        }

        $markup = $this->compactElement($node, $depth, $wrap, $writing);
        $condition = $substitutions->condition($node);

        return $condition === null
            ? $markup
            : sprintf('<f:if condition="%s">%s</f:if>', $this->escape($condition), $markup);
    }

    /**
     * The element with its children on one line (tags may still wrap their
     * attributes), without a condition wrapped around it.
     */
    private function compactElement(Node $node, int $depth, bool $wrap, MarkupWriting $writing): string
    {
        $name = $writing->casing->restoreTag($node->name);
        $opening = $this->openingTag($node, $depth, $wrap, $writing);

        $children = '';
        foreach ($node->children() as $child) {
            $content = $this->compact($child, $depth, $wrap, $writing);
            if ($content === '') {
                continue;
            }
            $children .= ($node->hasWhitespaceBefore($child) ? ' ' : '') . $content;
        }
        if ($children !== '' && $node->hasTrailingWhitespace()) {
            $children .= ' ';
        }

        return match (true) {
            in_array($node->name, self::VOID_ELEMENTS, true) => $opening . ' />',
            trim($children) === '' && str_contains($node->name, ':') => $opening . ' />',
            default => $opening . '>' . $children . '</' . $name . '>',
        };
    }

    /**
     * The opening tag without its closing bracket. When wrapping is allowed and
     * the tag is long, each attribute goes on a line of its own.
     */
    private function openingTag(Node $node, int $depth, bool $wrap, MarkupWriting $writing): string
    {
        $name = '<' . $writing->casing->restoreTag($node->name);
        $attributes = [];
        foreach ($node->attributes as $attribute => $value) {
            $value = $writing->substitutions->attributeOverride($node, $attribute)
                ?? $writing->substitutions->applyRenames($value);
            $attributes[] = sprintf('%s="%s"', $writing->casing->restoreAttribute($attribute), $this->escape($value));
        }

        $single = $name . ($attributes === [] ? '' : ' ' . implode(' ', $attributes));
        if (!$wrap || count($attributes) < 2 || strlen($single) <= self::MAX_TAG) {
            return $single;
        }

        $attributeIndent = "\n" . str_repeat(self::INDENT, $depth + 1);

        return $name . $attributeIndent . implode($attributeIndent, $attributes);
    }

    private function text(Node $node, MarkupWriting $writing): string
    {
        $text = $writing->substitutions->textOverride($node) ?? $writing->substitutions->applyRenames($node->text);

        return str_replace("\u{00A0}", '&nbsp;', $text);
    }

    /**
     * A child that gets a line of its own: a block element, an element holding
     * one, or an element wrapped in a condition.
     */
    private function isBlockNode(Node $node, MarkupWriting $writing): bool
    {
        $substitutions = $writing->substitutions;
        if (!$node->isElement() || $substitutions->isDropped($node) || $substitutions->replacement($node) !== null) {
            return false;
        }

        return in_array($node->name, self::BLOCK_ELEMENTS, true)
            || $substitutions->condition($node) !== null
            || $this->hasBlockDescendant($node, $writing);
    }

    private function hasBlockDescendant(Node $node, MarkupWriting $writing): bool
    {
        foreach ($node->children() as $child) {
            if ($this->isBlockNode($child, $writing)) {
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
