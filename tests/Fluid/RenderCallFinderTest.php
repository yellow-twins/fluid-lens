<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Fluid;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Fluid\RenderCall;
use YellowTwins\FluidLens\Fluid\RenderCallFinder;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class RenderCallFinderTest extends TestCase
{
    /**
     * @return list<RenderCall>
     */
    private function calls(string $template): array
    {
        $parsed = new ParsedTemplate('a.html', (new TemplateParser())->parse($template), $template);

        return (new RenderCallFinder())->in($parsed);
    }

    public function testReadsTheTagForm(): void
    {
        $calls = $this->calls(
            "<div>\n<f:render partial=\"/Event/Teaser\""
                . " arguments=\"{event: item, label: 'More', count: 3}\" />\n</div>",
        );

        self::assertCount(1, $calls);
        self::assertSame('Event/Teaser', $calls[0]->partial);
        self::assertSame(2, $calls[0]->line);
        self::assertSame(['event' => '{item}', 'label' => 'More', 'count' => '3'], $calls[0]->arguments);
        self::assertTrue($calls[0]->argumentsAreKnown());
    }

    public function testReadsTheInlineAndChainedForms(): void
    {
        $calls = $this->calls(
            "<p>{f:render(partial: 'Price', arguments: {product: product})}</p>"
            . "<p>{product -> f:render(partial: 'Badge', arguments: _all)}</p>",
        );

        self::assertSame(['Price', 'Badge'], [$calls[0]->partial, $calls[1]->partial]);
        self::assertSame(['product' => '{product}'], $calls[0]->arguments);
        self::assertTrue($calls[1]->passesAll);
        self::assertFalse($calls[1]->argumentsAreKnown());
    }

    public function testMarksDynamicNamesAndSectionCalls(): void
    {
        $calls = $this->calls('<f:render partial="Item/{type}" /><f:render section="Main" />');

        self::assertTrue($calls[0]->isDynamic());
        self::assertFalse($calls[1]->isPartialCall());
        self::assertSame('Main', $calls[1]->section);
    }

    public function testACallWithoutArgumentsPassesNothing(): void
    {
        $calls = $this->calls('<f:render partial="Footer" />');

        self::assertSame([], $calls[0]->argumentNames());
        self::assertTrue($calls[0]->argumentsAreKnown());
    }
}
