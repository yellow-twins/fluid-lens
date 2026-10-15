<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Draft;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Draft\FluidMarkupWriter;
use YellowTwins\FluidLens\Component\Draft\Substitutions;
use YellowTwins\FluidLens\Fluid\SourceCasing;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class FluidMarkupWriterTest extends TestCase
{
    private function write(string $source, ?Substitutions $substitutions = null): string
    {
        $tree = (new TemplateParser())->parse($source);

        return (new FluidMarkupWriter())->write(
            $tree->children(),
            $substitutions ?? new Substitutions(),
            SourceCasing::fromSource($source),
        );
    }

    public function testIndentsBlocksAndKeepsInlineContentTogether(): void
    {
        $source = '<div class="card"><h2>{title}</h2><p><a href="{url}">{label}</a>, more</p></div>';

        self::assertSame(
            "<div class=\"card\">\n    <h2>{title}</h2>\n    <p><a href=\"{url}\">{label}</a>, more</p>\n</div>",
            $this->write($source),
        );
    }

    public function testRestoresSpellingAndSelfClosesViewHelpers(): void
    {
        $source = '<p><f:translate key="a" extensionName="site" /><br><st:backHeadline title="x" /></p>';

        self::assertSame(
            '<p><f:translate key="a" extensionName="site" /><br /><st:backHeadline title="x" /></p>',
            $this->write($source),
        );
    }

    public function testAppliesSubstitutions(): void
    {
        $source = '<div class="box"><a href="{settings.pid}">Go</a><span>old</span><i>x</i></div>';
        $tree = (new TemplateParser())->parse($source);
        $div = $tree->elementChildren()[0];
        [$link, $span, $icon] = $div->elementChildren();

        $substitutions = new Substitutions();
        $substitutions->renameVariable('settings.pid', 'pid');
        $substitutions->setAttribute($div, 'class', '{class}');
        $substitutions->replace($span, '<f:slot />');
        $substitutions->wrapInCondition($icon, '{showIcon}');
        $substitutions->setText($link->children()[0], '{label}');

        self::assertSame(
            "<div class=\"{class}\">\n    <a href=\"{pid}\">{label}</a><f:slot />\n"
                . "    <f:if condition=\"{showIcon}\">\n"
                . "        <i>x</i>\n    </f:if>\n</div>",
            (new FluidMarkupWriter())->write($tree->children(), $substitutions, SourceCasing::fromSource($source)),
        );
    }

    public function testDropsNodesAndKeepsNonBreakingSpaces(): void
    {
        $source = '<p>a&nbsp;b<f:comment>note</f:comment></p>';
        $tree = (new TemplateParser())->parse($source);
        $substitutions = new Substitutions();
        $substitutions->drop($tree->elementChildren()[0]->elementChildren()[0]);

        self::assertSame(
            '<p>a&nbsp;b</p>',
            (new FluidMarkupWriter())->write($tree->children(), $substitutions, SourceCasing::none()),
        );
    }
}
