<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Wcag\AbbrTitleRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaBooleanRule;
use YellowTwins\FluidLens\Rule\Wcag\DirValidRule;
use YellowTwins\FluidLens\Rule\Wcag\LabelEmptyRule;
use YellowTwins\FluidLens\Rule\Wcag\LangValidRule;
use YellowTwins\FluidLens\Rule\Wcag\MetaRefreshRule;
use YellowTwins\FluidLens\Rule\Wcag\ScopeValueRule;
use YellowTwins\FluidLens\Rule\Wcag\SummaryDetailsRule;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class WcagBatchTwoTest extends TestCase
{
    public function testLangValid(): void
    {
        self::assertCount(1, $this->runRule(new LangValidRule(), '<span lang="german">x</span>'));
        self::assertSame([], $this->runRule(new LangValidRule(), '<span lang="en-US">x</span>'));
    }

    public function testLabelEmpty(): void
    {
        self::assertCount(1, $this->runRule(new LabelEmptyRule(), '<label for="x"></label>'));
        self::assertSame([], $this->runRule(new LabelEmptyRule(), '<label for="x">Name</label>'));
    }

    public function testAriaBoolean(): void
    {
        self::assertCount(1, $this->runRule(new AriaBooleanRule(), '<div aria-expanded="yes">x</div>'));
        self::assertSame([], $this->runRule(new AriaBooleanRule(), '<div aria-expanded="true">x</div>'));
        self::assertSame([], $this->runRule(new AriaBooleanRule(), '<div aria-checked="mixed">x</div>'));
    }

    public function testDirValid(): void
    {
        self::assertCount(1, $this->runRule(new DirValidRule(), '<div dir="leftright">x</div>'));
        self::assertSame([], $this->runRule(new DirValidRule(), '<div dir="rtl">x</div>'));
    }

    public function testMetaRefresh(): void
    {
        self::assertCount(1, $this->runRule(new MetaRefreshRule(), '<meta http-equiv="refresh" content="5"/>'));
        self::assertSame([], $this->runRule(new MetaRefreshRule(), '<meta charset="utf-8"/>'));
    }

    public function testSummaryDetails(): void
    {
        self::assertCount(1, $this->runRule(new SummaryDetailsRule(), '<div><summary>More</summary></div>'));
        self::assertSame([], $this->runRule(new SummaryDetailsRule(), '<details><summary>More</summary></details>'));
    }

    public function testScopeValue(): void
    {
        self::assertCount(1, $this->runRule(new ScopeValueRule(), '<table><tr><th scope="colum">x</th></tr></table>'));
        self::assertSame([], $this->runRule(new ScopeValueRule(), '<table><tr><th scope="col">x</th></tr></table>'));
    }

    public function testAbbrTitleIsAaaNotice(): void
    {
        self::assertCount(1, $this->runRule(new AbbrTitleRule(), '<abbr>WCAG</abbr>'));
        self::assertSame([], $this->runRule(new AbbrTitleRule(), '<abbr title="Web Content ...">WCAG</abbr>'));
    }

    /**
     * @return list<\YellowTwins\FluidLens\Rule\Finding>
     */
    private function runRule(Rule $rule, string $source): array
    {
        return $rule->check(new ParsedTemplate('t.html', (new TemplateParser())->parse($source)));
    }
}
