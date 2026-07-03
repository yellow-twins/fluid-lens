<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\BestPractice\InlineStyleRule;
use YellowTwins\FluidLens\Rule\BestPractice\InlineSvgRule;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Rule\Wcag\DuplicateIdRule;
use YellowTwins\FluidLens\Rule\Wcag\FormLabelRule;
use YellowTwins\FluidLens\Rule\Wcag\HeadingOrderRule;
use YellowTwins\FluidLens\Rule\Wcag\HtmlLangRule;
use YellowTwins\FluidLens\Rule\Wcag\ImageAltRule;
use YellowTwins\FluidLens\Rule\Wcag\LinkNameRule;
use YellowTwins\FluidLens\Rule\Wcag\PositiveTabindexRule;
use YellowTwins\FluidLens\Rule\Wcag\TableHeaderRule;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class RulesTest extends TestCase
{
    public function testImageWithoutAltIsAnError(): void
    {
        $findings = $this->runRule(new ImageAltRule(), '<f:image src="a.jpg"/>');

        self::assertCount(1, $findings);
        self::assertSame(Severity::Error, $findings[0]->severity);
    }

    public function testDecorativeImageWithEmptyAltIsAllowed(): void
    {
        self::assertSame([], $this->runRule(new ImageAltRule(), '<img src="a.jpg" alt=""/>'));
    }

    public function testIconOnlyLinkIsAnError(): void
    {
        $findings = $this->runRule(new LinkNameRule(), '<a href="/x"><svg><path d="M0 0"/></svg></a>');

        self::assertCount(1, $findings);
        self::assertSame('WCAG 2.4.4 (A)', $findings[0]->reference);
    }

    public function testLinkWithTextOrAriaLabelIsAllowed(): void
    {
        $ariaLabelled = '<a href="/x" aria-label="Home"><svg><path/></svg></a>';
        self::assertSame([], $this->runRule(new LinkNameRule(), '<a href="/x">Read more</a>'));
        self::assertSame([], $this->runRule(new LinkNameRule(), $ariaLabelled));
        self::assertSame([], $this->runRule(new LinkNameRule(), '<a href="/x">{product.title}</a>'));
    }

    public function testUnlabelledControlWarnsButLabelledOneDoesNot(): void
    {
        self::assertCount(1, $this->runRule(new FormLabelRule(), '<input type="text"/>'));
        self::assertSame([], $this->runRule(new FormLabelRule(), '<input type="text" id="name"/>'));
        self::assertSame([], $this->runRule(new FormLabelRule(), '<input type="hidden"/>'));
    }

    public function testHtmlWithoutLangWarns(): void
    {
        self::assertCount(1, $this->runRule(new HtmlLangRule(), '<html><body>x</body></html>'));
        self::assertSame([], $this->runRule(new HtmlLangRule(), '<html lang="de"><body>x</body></html>'));
        // The Fluid namespace-declaration wrapper is not the document root.
        $wrapper = '<html data-namespace-typo3-fluid="true"><body>x</body></html>';
        self::assertSame([], $this->runRule(new HtmlLangRule(), $wrapper));
    }

    public function testPositiveTabindexWarnsOnlyWhenPositiveAndStatic(): void
    {
        self::assertCount(1, $this->runRule(new PositiveTabindexRule(), '<div tabindex="3">x</div>'));
        self::assertSame([], $this->runRule(new PositiveTabindexRule(), '<div tabindex="0">x</div>'));
        self::assertSame([], $this->runRule(new PositiveTabindexRule(), '<div tabindex="-1">x</div>'));
        self::assertSame([], $this->runRule(new PositiveTabindexRule(), '<div tabindex="{i}">x</div>'));
    }

    public function testTableWithoutHeadersWarns(): void
    {
        self::assertCount(1, $this->runRule(new TableHeaderRule(), '<table><tr><td>a</td></tr></table>'));
        self::assertSame([], $this->runRule(new TableHeaderRule(), '<table><tr><th>a</th></tr></table>'));
        $presentation = '<table role="presentation"><tr><td>a</td></tr></table>';
        self::assertSame([], $this->runRule(new TableHeaderRule(), $presentation));
    }

    public function testDuplicateStaticIdIsAnError(): void
    {
        $findings = $this->runRule(new DuplicateIdRule(), '<div id="x"></div><div id="x"></div>');
        self::assertCount(2, $findings);

        self::assertSame([], $this->runRule(new DuplicateIdRule(), '<div id="{a}"></div><div id="{a}"></div>'));
    }

    public function testHeadingLevelSkipWarns(): void
    {
        self::assertCount(1, $this->runRule(new HeadingOrderRule(), '<div><h2>a</h2><h4>b</h4></div>'));
        self::assertSame([], $this->runRule(new HeadingOrderRule(), '<div><h2>a</h2><h3>b</h3></div>'));
    }

    public function testBestPracticeSnipsAreNotices(): void
    {
        $style = $this->runRule(new InlineStyleRule(), '<div style="color:red">x</div>');
        self::assertSame(Severity::Notice, $style[0]->severity);

        $svg = $this->runRule(new InlineSvgRule(), '<svg><path d="M0 0"/></svg>');
        self::assertSame(Severity::Notice, $svg[0]->severity);
    }

    /**
     * @return list<\YellowTwins\FluidLens\Rule\Finding>
     */
    private function runRule(Rule $rule, string $source): array
    {
        $tree = (new TemplateParser())->parse($source);

        return $rule->check(new ParsedTemplate('template.html', $tree));
    }
}
