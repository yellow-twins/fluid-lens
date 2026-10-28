<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Source;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Source\Segment;
use YellowTwins\FluidLens\Source\SegmentType;
use YellowTwins\FluidLens\Source\SourceScanner;

final class SourceScannerTest extends TestCase
{
    public function testSegmentsCoverTheWholeSourceInOrder(): void
    {
        $source = "<p class=\"a\">Hello {name}!</p>\n<!-- note --><br/>";
        $segments = (new SourceScanner())->scan($source)->segments;

        self::assertSame($source, implode('', array_map(static fn (Segment $s): string => $s->text, $segments)));
        self::assertSame(
            ['tag', 'text', 'expression', 'text', 'tag', 'text', 'comment', 'tag'],
            array_map(static fn (Segment $s): string => $s->type->value, $segments),
        );
    }

    public function testAttributeValuesKeepTheirOffsetsEvenWithBracketsInside(): void
    {
        $source = '<a title="{f:if(condition: \'{a} > 1\', then: \'x\')}" href=/x>';
        $tag = (new SourceScanner())->scan($source)->segments[0]->tag;

        self::assertNotNull($tag);
        $title = $tag->attribute('title');
        self::assertNotNull($title);
        self::assertSame($title->value, substr($source, $title->valueStart, $title->valueEnd - $title->valueStart));
        self::assertSame('"', $title->quote);
        self::assertSame('/x', $tag->attributeValue('href'));
    }

    public function testSelfClosingViewHelpersAndClosingTags(): void
    {
        $segments = (new SourceScanner())->scan('<f:image src="a" /></f:link.page>')->segments;

        self::assertTrue($segments[0]->tag?->isSelfClosing);
        self::assertSame('f:link.page', $segments[1]->tag?->name);
        self::assertTrue($segments[1]->tag->isClosing);
    }

    public function testScriptStyleCommentAndCdataBodiesAreRaw(): void
    {
        $source = '<script>if (a < b) { x(); }</script><f:comment><p>x</p></f:comment><![CDATA[ <b> ]]>';
        $types = array_map(
            static fn (Segment $s): string => $s->type->value,
            (new SourceScanner())->scan($source)->segments,
        );

        self::assertSame(['tag', 'raw', 'tag', 'tag', 'raw', 'tag', 'raw'], $types);
    }

    public function testAViewHelperInsideATagDoesNotEndIt(): void
    {
        $source = '<div class="a"<f:if condition="{x}"> style="b"</f:if>>Text</div>';
        $segments = (new SourceScanner())->scan($source)->segments;

        self::assertSame('div', $segments[0]->tag?->name);
        self::assertSame('b', $segments[0]->tag->attributeValue('style'));
        self::assertSame(SegmentType::Text, $segments[1]->type);
        self::assertSame('Text', $segments[1]->text);
    }

    public function testBrokenMarkupStaysText(): void
    {
        $segments = (new SourceScanner())->scan('a < b and { unclosed <p title="x')->segments;

        self::assertCount(1, $segments);
        self::assertSame(SegmentType::Text, $segments[0]->type);
    }

    public function testResolvesLinesFromOffsets(): void
    {
        $scanned = (new SourceScanner())->scan("one\ntwo\nthree");

        self::assertSame(1, $scanned->lineOf(0));
        self::assertSame(2, $scanned->lineOf(4));
        self::assertSame(3, $scanned->lineOf(12));
    }
}
