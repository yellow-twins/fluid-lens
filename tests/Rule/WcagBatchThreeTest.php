<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Wcag\AccesskeyDuplicateRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaControlsTargetRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaRefTargetRule;
use YellowTwins\FluidLens\Rule\Wcag\AutocompleteTokenRule;
use YellowTwins\FluidLens\Rule\Wcag\LangXmlMismatchRule;
use YellowTwins\FluidLens\Rule\Wcag\NavLabelRule;
use YellowTwins\FluidLens\Rule\Wcag\TargetBlankPurposeRule;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class WcagBatchThreeTest extends TestCase
{
    public function testAutocompleteToken(): void
    {
        self::assertCount(1, $this->runRule(new AutocompleteTokenRule(), '<input autocomplete="e-mail"/>'));
        self::assertSame([], $this->runRule(new AutocompleteTokenRule(), '<input autocomplete="email"/>'));
        $shipping = '<input autocomplete="shipping postal-code"/>';
        self::assertSame([], $this->runRule(new AutocompleteTokenRule(), $shipping));
        self::assertSame([], $this->runRule(new AutocompleteTokenRule(), '<input autocomplete="off"/>'));
        self::assertSame([], $this->runRule(new AutocompleteTokenRule(), '<input autocomplete="{dynamic}"/>'));
    }

    public function testLangXmlMismatch(): void
    {
        self::assertCount(1, $this->runRule(new LangXmlMismatchRule(), '<span lang="en" xml:lang="de">x</span>'));
        self::assertSame([], $this->runRule(new LangXmlMismatchRule(), '<span lang="en" xml:lang="EN">x</span>'));
        self::assertSame([], $this->runRule(new LangXmlMismatchRule(), '<span lang="en">x</span>'));
    }

    public function testAccesskeyDuplicate(): void
    {
        $source = '<div><button accesskey="s">a</button><button accesskey="S">b</button></div>';
        self::assertCount(2, $this->runRule(new AccesskeyDuplicateRule(), $source));

        $unique = '<div><button accesskey="s">a</button><button accesskey="n">b</button></div>';
        self::assertSame([], $this->runRule(new AccesskeyDuplicateRule(), $unique));
    }

    public function testTargetBlankPurpose(): void
    {
        self::assertCount(1, $this->runRule(new TargetBlankPurposeRule(), '<a href="/x" target="_blank">Report</a>'));
        self::assertSame(
            [],
            $this->runRule(new TargetBlankPurposeRule(), '<a href="/x" target="_blank" title="opens">Report</a>'),
        );
        self::assertSame(
            [],
            $this->runRule(new TargetBlankPurposeRule(), '<a href="/x" target="_blank">Report (new tab)</a>'),
        );
        self::assertSame([], $this->runRule(new TargetBlankPurposeRule(), '<a href="/x">Report</a>'));
    }

    public function testAriaControlsTarget(): void
    {
        self::assertCount(1, $this->runRule(new AriaControlsTargetRule(), '<button aria-controls="panel">x</button>'));
        $matched = '<div><button aria-controls="panel">x</button><div id="panel">p</div></div>';
        self::assertSame([], $this->runRule(new AriaControlsTargetRule(), $matched));
        self::assertSame([], $this->runRule(new AriaControlsTargetRule(), '<button aria-controls="{id}">x</button>'));
    }

    public function testAriaRefTarget(): void
    {
        self::assertCount(1, $this->runRule(new AriaRefTargetRule(), '<div aria-labelledby="ttl">x</div>'));
        $matched = '<div><h2 id="ttl">T</h2><section aria-labelledby="ttl">x</section></div>';
        self::assertSame([], $this->runRule(new AriaRefTargetRule(), $matched));
    }

    public function testNavLabel(): void
    {
        $twoUnlabelled = '<div><nav>a</nav><nav>b</nav></div>';
        self::assertCount(2, $this->runRule(new NavLabelRule(), $twoUnlabelled));

        $labelled = '<div><nav aria-label="Main">a</nav><nav aria-label="Footer">b</nav></div>';
        self::assertSame([], $this->runRule(new NavLabelRule(), $labelled));

        // A single nav needs no distinguishing label.
        self::assertSame([], $this->runRule(new NavLabelRule(), '<nav>a</nav>'));
    }

    /**
     * @return list<\YellowTwins\FluidLens\Rule\Finding>
     */
    private function runRule(Rule $rule, string $source): array
    {
        return $rule->check(new ParsedTemplate('t.html', (new TemplateParser())->parse($source)));
    }
}
