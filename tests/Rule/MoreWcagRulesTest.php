<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Wcag\AltFilenameRule;
use YellowTwins\FluidLens\Rule\Wcag\FieldsetLegendRule;
use YellowTwins\FluidLens\Rule\Wcag\InputImageAltRule;
use YellowTwins\FluidLens\Rule\Wcag\LinkGenericTextRule;
use YellowTwins\FluidLens\Rule\Wcag\ListStructureRule;
use YellowTwins\FluidLens\Rule\Wcag\MarqueeBlinkRule;
use YellowTwins\FluidLens\Rule\Wcag\NestedInteractiveRule;
use YellowTwins\FluidLens\Rule\Wcag\RoleRequiredAttrRule;
use YellowTwins\FluidLens\Rule\Wcag\ThEmptyRule;
use YellowTwins\FluidLens\Rule\Wcag\VideoCaptionsRule;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class MoreWcagRulesTest extends TestCase
{
    public function testAltFilename(): void
    {
        self::assertCount(1, $this->runRule(new AltFilenameRule(), '<img src="a" alt="hero.jpg"/>'));
        self::assertSame([], $this->runRule(new AltFilenameRule(), '<img src="a" alt="Our team on stage"/>'));
    }

    public function testInputImageAlt(): void
    {
        self::assertCount(1, $this->runRule(new InputImageAltRule(), '<input type="image" src="go.png"/>'));
        $labelled = '<input type="image" src="go.png" alt="Search"/>';
        self::assertSame([], $this->runRule(new InputImageAltRule(), $labelled));
    }

    public function testLinkGenericText(): void
    {
        self::assertCount(1, $this->runRule(new LinkGenericTextRule(), '<a href="/x">read more</a>'));
        self::assertSame([], $this->runRule(new LinkGenericTextRule(), '<a href="/x">read the annual report</a>'));
    }

    public function testNestedInteractive(): void
    {
        self::assertCount(1, $this->runRule(new NestedInteractiveRule(), '<a href="/x"><button>Go</button></a>'));
        self::assertSame([], $this->runRule(new NestedInteractiveRule(), '<a href="/x"><span>Go</span></a>'));
    }

    public function testFieldsetLegend(): void
    {
        self::assertCount(1, $this->runRule(new FieldsetLegendRule(), '<fieldset><input/></fieldset>'));
        $ok = '<fieldset><legend>Name</legend><input/></fieldset>';
        self::assertSame([], $this->runRule(new FieldsetLegendRule(), $ok));
    }

    public function testListStructureAllowsViewHelperChildren(): void
    {
        self::assertCount(1, $this->runRule(new ListStructureRule(), '<ul><div>x</div></ul>'));
        self::assertSame([], $this->runRule(new ListStructureRule(), '<ul><li>x</li></ul>'));
        $viewHelper = '<ul><f:for each="{a}" as="i"><li>x</li></f:for></ul>';
        self::assertSame([], $this->runRule(new ListStructureRule(), $viewHelper));
    }

    public function testThEmpty(): void
    {
        self::assertCount(1, $this->runRule(new ThEmptyRule(), '<table><tr><th></th></tr></table>'));
        self::assertSame([], $this->runRule(new ThEmptyRule(), '<table><tr><th>Name</th></tr></table>'));
    }

    public function testRoleRequiredAttr(): void
    {
        $checked = '<div role="checkbox" aria-checked="false">x</div>';
        self::assertCount(1, $this->runRule(new RoleRequiredAttrRule(), '<div role="checkbox">x</div>'));
        self::assertSame([], $this->runRule(new RoleRequiredAttrRule(), $checked));
        self::assertCount(1, $this->runRule(new RoleRequiredAttrRule(), '<div role="slider">x</div>'));
    }

    public function testVideoCaptions(): void
    {
        self::assertCount(1, $this->runRule(new VideoCaptionsRule(), '<video src="a.mp4"></video>'));
        $ok = '<video src="a.mp4"><track kind="captions" src="c.vtt"/></video>';
        self::assertSame([], $this->runRule(new VideoCaptionsRule(), $ok));
    }

    public function testMarqueeBlink(): void
    {
        self::assertCount(1, $this->runRule(new MarqueeBlinkRule(), '<marquee>news</marquee>'));
        self::assertSame([], $this->runRule(new MarqueeBlinkRule(), '<div>news</div>'));
    }

    /**
     * @return list<\YellowTwins\FluidLens\Rule\Finding>
     */
    private function runRule(Rule $rule, string $source): array
    {
        return $rule->check(new ParsedTemplate('t.html', (new TemplateParser())->parse($source)));
    }
}
