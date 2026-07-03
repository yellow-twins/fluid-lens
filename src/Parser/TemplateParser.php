<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Parser;

use DOMAttr;
use DOMComment;
use DOMElement;
use DOMNode;
use DOMText;
use Masterminds\HTML5;

/**
 * Parses a Fluid template into a lightweight structural {@see Node} tree.
 *
 * Fluid is not valid XML and Fluid itself only parses ViewHelpers, treating the
 * surrounding HTML as plain text. To reason about markup structure (the nesting
 * of divs, sections and ViewHelpers) we parse with a tolerant HTML5 parser and
 * keep Fluid tags as ordinary elements and {@code {expressions}} as opaque text.
 */
final class TemplateParser
{
    /**
     * Matches a self-closing, namespaced tag such as {@code <f:image ... />}.
     *
     * Fluid ViewHelpers always carry a namespace prefix ({@code f:}, {@code core:}, ...)
     * and use XML self-closing syntax, which an HTML5 parser ignores for unknown
     * elements — that would wrongly nest every following sibling inside the tag.
     * The attribute part tolerates quoted values that themselves contain
     * {@code >} or {@code /}.
     */
    private const SELF_CLOSING_VIEWHELPER = '/<([a-zA-Z][\w.-]*:[\w.:-]+)((?:[^>"\']|"[^"]*"|\'[^\']*\')*?)\/>/';

    /**
     * Matches the name portion of any opening or closing namespaced tag, so the
     * dots inside it can be protected before parsing.
     */
    private const NAMESPACED_TAG_NAME = '/<(\/?)([a-zA-Z][\w.-]*:[\w.:-]*)/';

    /**
     * Placeholder substituted for the dots in a Fluid tag name while parsing.
     *
     * The HTML5 tokenizer truncates tag names at a dot ({@code f:link.typolink}
     * becomes {@code f:link}), which would collapse distinct ViewHelpers into one.
     * Lowercase letters are used so the tokenizer's own lowercasing leaves it intact.
     */
    private const DOT_PLACEHOLDER = 'fldotmarker';

    private readonly HTML5 $html5;

    public function __construct()
    {
        $this->html5 = new HTML5(['disable_html_ns' => true]);
    }

    public function parseFile(string $path): Node
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            throw new ParseException(sprintf('Cannot read template file "%s".', $path));
        }

        return $this->parse($source);
    }

    public function parse(string $source): Node
    {
        $prepared = $this->normalizeSelfClosingTags($this->protectViewHelperDots($source));
        $fragment = $this->html5->loadHTMLFragment($prepared);

        // Line numbers are resolved against the original, unmodified source.
        $lines = new SourceLineResolver($source);

        $root = new Node(NodeType::Root);
        foreach ($fragment->childNodes as $child) {
            $this->appendConverted($root, $child, $lines);
        }

        return $root;
    }

    /**
     * Rewrites self-closing ViewHelper tags into an explicit open/close pair so
     * the HTML5 parser nests siblings correctly.
     */
    private function normalizeSelfClosingTags(string $source): string
    {
        $normalized = preg_replace(self::SELF_CLOSING_VIEWHELPER, '<$1$2></$1>', $source);

        return $normalized ?? $source;
    }

    /**
     * Replaces the dots inside namespaced tag names with a placeholder so the
     * HTML5 tokenizer keeps the full name; {@see restoreViewHelperDots()} reverses it.
     */
    private function protectViewHelperDots(string $source): string
    {
        $protected = preg_replace_callback(
            self::NAMESPACED_TAG_NAME,
            static fn (array $match): string
                => '<' . $match[1] . str_replace('.', self::DOT_PLACEHOLDER, $match[2]),
            $source,
        );

        return $protected ?? $source;
    }

    private function restoreViewHelperDots(string $name): string
    {
        return str_replace(self::DOT_PLACEHOLDER, '.', $name);
    }

    private function appendConverted(Node $parent, DOMNode $domNode, SourceLineResolver $lines): void
    {
        $converted = $this->convert($domNode, $lines);
        if ($converted !== null) {
            $parent->addChild($converted);
        }
    }

    private function convert(DOMNode $domNode, SourceLineResolver $lines): ?Node
    {
        if ($domNode instanceof DOMElement) {
            return $this->convertElement($domNode, $lines);
        }

        if ($domNode instanceof DOMComment) {
            return new Node(NodeType::Comment, text: $domNode->textContent);
        }

        if ($domNode instanceof DOMText) {
            return $this->convertText($domNode);
        }

        return null;
    }

    private function convertElement(DOMElement $element, SourceLineResolver $lines): Node
    {
        $name = $this->restoreViewHelperDots($element->nodeName);

        $node = new Node(
            NodeType::Element,
            $name,
            $this->extractAttributes($element),
            sourceRange: $this->sourceRange($lines->resolve($name)),
        );

        foreach ($element->childNodes as $child) {
            $this->appendConverted($node, $child, $lines);
        }

        return $node;
    }

    private function convertText(DOMText $text): ?Node
    {
        // Whitespace-only text carries no structural meaning and only adds noise
        // to comparisons, so it is dropped; significant text is kept verbatim.
        if (trim($text->textContent) === '') {
            return null;
        }

        return new Node(NodeType::Text, text: $text->textContent);
    }

    /**
     * @return array<string, string>
     */
    private function extractAttributes(DOMElement $element): array
    {
        $attributes = [];
        $map = $element->attributes;

        /** @var DOMAttr $attribute */
        foreach ($map as $attribute) {
            $attributes[$attribute->nodeName] = (string) $attribute->nodeValue;
        }

        return $attributes;
    }

    private function sourceRange(?int $line): ?SourceRange
    {
        return $line !== null ? new SourceRange($line) : null;
    }
}
