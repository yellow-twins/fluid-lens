<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Parser;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class TemplateParserTest extends TestCase
{
    private TemplateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
    }

    public function testParsesNestedElementsIntoTree(): void
    {
        $tree = $this->parser->parse('<div class="a"><span>Hello</span></div>');

        self::assertSame(NodeType::Root, $tree->type);

        $div = $tree->children()[0];
        self::assertSame(NodeType::Element, $div->type);
        self::assertSame('div', $div->name);
        self::assertSame('a', $div->attribute('class'));

        $span = $div->elementChildren()[0];
        self::assertSame('span', $span->name);
    }

    public function testKeepsFluidViewHelperTagsAsElements(): void
    {
        $tree = $this->parser->parse('<f:image src="EXT:x/foo.svg" alt="bg"/>');

        $image = $tree->children()[0];
        self::assertSame(NodeType::Element, $image->type);
        self::assertSame('f:image', $image->name);
        self::assertSame('bg', $image->attribute('alt'));
    }

    public function testPreservesDottedViewHelperNames(): void
    {
        // f:link.typolink and f:format.html must stay distinct: the HTML5
        // tokenizer would otherwise truncate both at the dot.
        $tree = $this->parser->parse(
            '<f:link.typolink parameter="1">a</f:link.typolink>'
            . '<f:format.html>b</f:format.html>',
        );

        $names = array_map(
            static fn ($child): string => $child->name,
            $tree->elementChildren(),
        );

        self::assertSame(['f:link.typolink', 'f:format.html'], $names);
    }

    public function testSelfClosingViewHelperDoesNotSwallowFollowingSiblings(): void
    {
        // The crux of parsing Fluid as HTML: a self-closing ViewHelper must not
        // adopt the elements that follow it as children.
        $tree = $this->parser->parse(
            '<div><f:image src="a.svg" alt="bg"/><div class="header">x</div></div>',
        );

        $wrapper = $tree->children()[0];
        $childNames = array_map(
            static fn ($child): string => $child->name,
            $wrapper->elementChildren(),
        );

        self::assertSame(['f:image', 'div'], $childNames);
    }

    public function testDropsInsignificantWhitespace(): void
    {
        $tree = $this->parser->parse("<div>\n    <span>x</span>\n</div>");

        $div = $tree->children()[0];
        self::assertCount(1, $div->children());
    }

    public function testResolvesSourceLineNumbers(): void
    {
        $tree = $this->parser->parse("<div>\n  <span>x</span>\n  <f:image src=\"a\"/>\n</div>");

        $div = $tree->elementChildren()[0];
        $span = $div->elementChildren()[0];
        $image = $div->elementChildren()[1];

        self::assertSame(1, $div->sourceRange?->startLine);
        self::assertSame(2, $span->sourceRange?->startLine);
        self::assertSame(3, $image->sourceRange?->startLine);
    }

    public function testDoesNotMisresolveTagNamesThatSharePrefixes(): void
    {
        // <a> must not be located at the <article> on line 1.
        $tree = $this->parser->parse("<article>\n  <a href=\"#\">x</a>\n</article>");

        $article = $tree->elementChildren()[0];
        $anchor = $article->elementChildren()[0];

        self::assertSame(1, $article->sourceRange?->startLine);
        self::assertSame(2, $anchor->sourceRange?->startLine);
    }

    public function testTreatsCurlyExpressionsAsOpaqueText(): void
    {
        $tree = $this->parser->parse('<div>{product.title}</div>');

        $text = $tree->children()[0]->children()[0];
        self::assertSame(NodeType::Text, $text->type);
        self::assertStringContainsString('{product.title}', $text->text);
    }
}
