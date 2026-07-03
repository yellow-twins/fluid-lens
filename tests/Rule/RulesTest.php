<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Rule\BestPractice\InlineStyleRule;
use YellowTwins\FluidLens\Rule\BestPractice\InlineSvgRule;
use YellowTwins\FluidLens\Rule\BestPractice\PreferFluidImageRule;
use YellowTwins\FluidLens\Rule\BestPractice\TargetBlankRelRule;
use YellowTwins\FluidLens\Rule\Markup\PictureImgRule;
use YellowTwins\FluidLens\Rule\Markup\PictureSourceSrcsetRule;
use YellowTwins\FluidLens\Rule\Wcag\EmptyHeadingRule;
use YellowTwins\FluidLens\Rule\Wcag\IframeTitleRule;
use YellowTwins\FluidLens\Rule\Wcag\MediaAutoplayRule;
use YellowTwins\FluidLens\Rule\Wcag\MetaViewportRule;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Rule\Wcag\AriaAttributeRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaHiddenFocusableRule;
use YellowTwins\FluidLens\Rule\Wcag\AriaRoleRule;
use YellowTwins\FluidLens\Rule\Wcag\ButtonNameRule;
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

    public function testIconOnlyButtonIsAnError(): void
    {
        self::assertCount(1, $this->runRule(new ButtonNameRule(), '<button><svg><path/></svg></button>'));
        self::assertSame([], $this->runRule(new ButtonNameRule(), '<button>Save</button>'));
        self::assertSame([], $this->runRule(new ButtonNameRule(), '<button aria-label="Close"><svg/></button>'));
    }

    public function testUnknownAriaRoleWarns(): void
    {
        self::assertCount(1, $this->runRule(new AriaRoleRule(), '<div role="buton">x</div>'));
        self::assertSame([], $this->runRule(new AriaRoleRule(), '<div role="button">x</div>'));
        self::assertSame([], $this->runRule(new AriaRoleRule(), '<div role="{dynamic}">x</div>'));
    }

    public function testUnknownAriaAttributeWarns(): void
    {
        self::assertCount(1, $this->runRule(new AriaAttributeRule(), '<div aria-lable="x">y</div>'));
        self::assertSame([], $this->runRule(new AriaAttributeRule(), '<div aria-label="x">y</div>'));
    }

    public function testAriaHiddenFocusableWarns(): void
    {
        $rule = new AriaHiddenFocusableRule();
        $focusableDiv = '<div tabindex="0" aria-hidden="true">i</div>';
        $optedOutLink = '<a href="/x" aria-hidden="true" tabindex="-1">i</a>';

        self::assertCount(1, $this->runRule($rule, '<a href="/x" aria-hidden="true">i</a>'));
        self::assertCount(1, $this->runRule($rule, $focusableDiv));
        self::assertSame([], $this->runRule($rule, '<span aria-hidden="true">i</span>'));
        self::assertSame([], $this->runRule($rule, $optedOutLink));
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

    public function testEmptyHeadingWarns(): void
    {
        self::assertCount(1, $this->runRule(new EmptyHeadingRule(), '<h2></h2>'));
        self::assertSame([], $this->runRule(new EmptyHeadingRule(), '<h2>Title</h2>'));
        self::assertSame([], $this->runRule(new EmptyHeadingRule(), '<h2>{dynamic}</h2>'));
    }

    public function testViewportBlockingZoomWarns(): void
    {
        $blocked = '<meta name="viewport" content="width=device-width, user-scalable=no"/>';
        $ok = '<meta name="viewport" content="width=device-width, initial-scale=1"/>';
        self::assertCount(1, $this->runRule(new MetaViewportRule(), $blocked));
        self::assertSame([], $this->runRule(new MetaViewportRule(), $ok));
    }

    public function testTargetBlankWithoutRelNoopenerIsANotice(): void
    {
        self::assertCount(1, $this->runRule(new TargetBlankRelRule(), '<a href="/x" target="_blank">x</a>'));
        $safe = '<a href="/x" target="_blank" rel="noopener">x</a>';
        self::assertSame([], $this->runRule(new TargetBlankRelRule(), $safe));
    }

    public function testIframeWithoutTitleWarns(): void
    {
        self::assertCount(1, $this->runRule(new IframeTitleRule(), '<iframe src="https://x"></iframe>'));
        self::assertSame([], $this->runRule(new IframeTitleRule(), '<iframe src="https://x" title="Map"></iframe>'));
    }

    public function testAutoplayingSoundWarns(): void
    {
        self::assertCount(1, $this->runRule(new MediaAutoplayRule(), '<audio autoplay src="a.mp3"></audio>'));
        self::assertCount(1, $this->runRule(new MediaAutoplayRule(), '<video autoplay src="a.mp4"></video>'));
        self::assertSame([], $this->runRule(new MediaAutoplayRule(), '<video autoplay muted src="a.mp4"></video>'));
        self::assertSame([], $this->runRule(new MediaAutoplayRule(), '<audio src="a.mp3"></audio>'));
    }

    public function testPictureWithoutImgFallbackWarns(): void
    {
        $broken = '<picture><source srcset="a.webp"/></picture>';
        $ok = '<picture><source srcset="a.webp"/><img src="a.jpg" alt="x"/></picture>';
        self::assertCount(1, $this->runRule(new PictureImgRule(), $broken));
        self::assertSame([], $this->runRule(new PictureImgRule(), $ok));
    }

    public function testPictureSourceWithoutSrcsetWarns(): void
    {
        $rule = new PictureSourceSrcsetRule();
        self::assertCount(1, $this->runRule($rule, '<picture><source media="(min-width:1px)"/></picture>'));
        self::assertSame([], $this->runRule($rule, '<picture><source srcset="a.webp"/></picture>'));
        // A <source> in <video> legitimately uses src, so it is left alone.
        self::assertSame([], $this->runRule($rule, '<video><source src="a.mp4"/></video>'));
    }

    public function testBestPracticeSnipsAreNotices(): void
    {
        $style = $this->runRule(new InlineStyleRule(), '<div style="color:red">x</div>');
        self::assertSame(Severity::Notice, $style[0]->severity);

        $svg = $this->runRule(new InlineSvgRule(), '<svg><path d="M0 0"/></svg>');
        self::assertSame(Severity::Notice, $svg[0]->severity);

        $img = $this->runRule(new PreferFluidImageRule(), '<img src="a.jpg" alt="x"/>');
        self::assertSame(Severity::Notice, $img[0]->severity);
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
