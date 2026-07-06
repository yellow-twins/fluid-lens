<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Consistency;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Consistency\Check\AnimationCheck;
use YellowTwins\FluidLens\Consistency\Check\CookieConsentCheck;
use YellowTwins\FluidLens\Consistency\Check\CssFrameworkCheck;
use YellowTwins\FluidLens\Consistency\Check\GridLayoutCheck;
use YellowTwins\FluidLens\Consistency\Check\IconSetCheck;
use YellowTwins\FluidLens\Consistency\Check\ImageApproachCheck;
use YellowTwins\FluidLens\Consistency\Check\JsFrameworkCheck;
use YellowTwins\FluidLens\Consistency\Check\LazyLoadCheck;
use YellowTwins\FluidLens\Consistency\Check\LightboxCheck;
use YellowTwins\FluidLens\Consistency\Check\LinkApproachCheck;
use YellowTwins\FluidLens\Consistency\Check\MapLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\NamespaceStyleCheck;
use YellowTwins\FluidLens\Consistency\Check\RenderStyleCheck;
use YellowTwins\FluidLens\Consistency\Check\SliderLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\TooltipLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\TranslateStyleCheck;
use YellowTwins\FluidLens\Consistency\Check\VideoPlayerCheck;
use YellowTwins\FluidLens\Consistency\ConsistencyRegistry;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class ConsistencyCheckTest extends TestCase
{
    public function testSliderCheckReportsOneLibraryAsConsistent(): void
    {
        $result = (new SliderLibraryCheck())->analyze([
            $this->template('a.html', '<div class="swiper"><div class="swiper-slide">1</div></div>'),
            $this->template('b.html', '<div class="swiper">x</div>'),
        ]);

        self::assertFalse($result->isInconsistent());
        self::assertSame('Swiper', $result->usages[0]->label);
        self::assertSame(2, $result->usages[0]->fileCount());
    }

    public function testIconCheckDetectsMixedSets(): void
    {
        $result = (new IconSetCheck())->analyze([
            $this->template('a.html', '<i class="fa fa-user"></i>'),
            $this->template('b.html', '<i class="bi bi-list"></i>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Font Awesome', $labels);
        self::assertContains('Bootstrap Icons', $labels);
    }

    public function testCssCheckDetectsBootstrapAndTailwindMix(): void
    {
        $result = (new CssFrameworkCheck())->analyze([
            $this->template('a.html', '<div class="row"><div class="col-md-6">x</div></div>'),
            $this->template('b.html', '<div class="md:flex bg-blue-500">y</div>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Bootstrap', $labels);
        self::assertContains('Tailwind', $labels);
    }

    public function testCssCheckIgnoresGenericUtilityClasses(): void
    {
        // "flex", "text-center", "container" are shared/generic — not a signal.
        $result = (new CssFrameworkCheck())->analyze([
            $this->template('a.html', '<div class="flex text-center container">x</div>'),
        ]);

        self::assertTrue($result->isEmpty());
    }

    public function testJsFrameworkCheckDetectsMixByAttributes(): void
    {
        $result = (new JsFrameworkCheck())->analyze([
            $this->template('a.html', '<div x-data="{}" x-show="open">a</div>'),
            $this->template('b.html', '<button hx-get="/more">b</button>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Alpine.js', $labels);
        self::assertContains('htmx', $labels);
    }

    public function testLightboxCheckDetectsByClassOrAttribute(): void
    {
        $result = (new LightboxCheck())->analyze([
            $this->template('a.html', '<a class="glightbox" href="x.jpg">a</a>'),
            $this->template('b.html', '<a data-fancybox="g" href="y.jpg">b</a>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('GLightbox', $labels);
        self::assertContains('Fancybox', $labels);
    }

    public function testAnimationCheckDetectsAosAndAnimateCss(): void
    {
        $result = (new AnimationCheck())->analyze([
            $this->template('a.html', '<div class="animate__animated animate__fadeIn">a</div>'),
            $this->template('b.html', '<div data-aos="fade-up">b</div>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Animate.css', $labels);
        self::assertContains('AOS', $labels);
    }

    public function testLazyLoadCheckDetectsNativeVsLibrary(): void
    {
        $result = (new LazyLoadCheck())->analyze([
            $this->template('a.html', '<img loading="lazy" src="a.jpg"/>'),
            $this->template('b.html', '<img class="lazyload" data-src="b.jpg"/>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Native (loading attribute)', $labels);
        self::assertContains('lazysizes', $labels);
    }

    public function testMapCheckDetectsMix(): void
    {
        $result = (new MapLibraryCheck())->analyze([
            $this->template('a.html', '<div class="leaflet-container">a</div>'),
            $this->template('b.html', '<div class="mapboxgl-map">b</div>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Leaflet', $labels);
        self::assertContains('Mapbox GL', $labels);
    }

    public function testVideoPlayerCheckDetectsMix(): void
    {
        $result = (new VideoPlayerCheck())->analyze([
            $this->template('a.html', '<div class="plyr">a</div>'),
            $this->template('b.html', '<div class="video-js vjs-default-skin">b</div>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Plyr', $labels);
        self::assertContains('Video.js', $labels);
    }

    public function testGridCheckReportsOneLibraryAsConsistent(): void
    {
        $result = (new GridLayoutCheck())->analyze([
            $this->template('a.html', '<div class="masonry">a</div>'),
            $this->template('b.html', '<div class="masonry">b</div>'),
        ]);

        self::assertFalse($result->isInconsistent());
        self::assertSame('Masonry', $result->usages[0]->label);
    }

    public function testTooltipCheckDetectsByAttributeAndIgnoresBootstrapToggle(): void
    {
        $result = (new TooltipLibraryCheck())->analyze([
            $this->template('a.html', '<button data-tippy-content="Hi">a</button>'),
            $this->template('b.html', '<span data-tooltip="Hint">b</span>'),
            // A generic Bootstrap toggle must not register as a tooltip library.
            $this->template('c.html', '<button data-bs-toggle="dropdown">c</button>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('Tippy.js', $labels);
        self::assertContains('Foundation', $labels);
        self::assertCount(2, $result->usages);
    }

    public function testCookieConsentCheckDetectsMix(): void
    {
        $result = (new CookieConsentCheck())->analyze([
            $this->template('a.html', '<div class="onetrust-banner">a</div>'),
            $this->template('b.html', '<div class="klaro">b</div>'),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('OneTrust', $labels);
        self::assertContains('Klaro', $labels);
    }

    public function testNamespaceStyleDetectsTagVsInline(): void
    {
        $result = (new NamespaceStyleCheck())->analyze([
            $this->template('a.html', '<html data-namespace-typo3-fluid="true"><body>a</body></html>'),
            $this->template('b.html', '{namespace x=Vendor\Ext\ViewHelpers}<div>b</div>'),
        ]);

        self::assertTrue($result->isInconsistent());
        self::assertCount(2, $result->usages);
    }

    public function testRenderStyleConsistentWhenOnlyTagForm(): void
    {
        $result = (new RenderStyleCheck())->analyze([
            $this->template('a.html', '<f:render partial="A"/>'),
            $this->template('b.html', '<f:render partial="B"/>'),
        ]);

        self::assertFalse($result->isInconsistent());
        self::assertSame('<f:render> tag', $result->usages[0]->label);
    }

    public function testRenderStyleDetectsInlineMix(): void
    {
        $result = (new RenderStyleCheck())->analyze([
            $this->template('a.html', '<f:render partial="A"/>'),
            $this->template('b.html', "<div>{f:render(partial: 'B')}</div>"),
        ]);

        self::assertTrue($result->isInconsistent());
    }

    public function testTranslateStyleDetectsInlineInAttribute(): void
    {
        $result = (new TranslateStyleCheck())->analyze([
            $this->template('a.html', '<f:translate key="hello"/>'),
            $this->template('b.html', "<img alt=\"{f:translate(key: 'x')}\" src=\"/a.png\"/>"),
        ]);

        self::assertTrue($result->isInconsistent());
        $labels = array_map(static fn ($usage): string => $usage->label, $result->usages);
        self::assertContains('<f:translate> tag', $labels);
        self::assertContains('{f:translate(...)} inline', $labels);
    }

    public function testImageApproachIgnoresStaticImg(): void
    {
        // A raw <img> with a static src is a legitimate asset, not a mixed approach.
        $result = (new ImageApproachCheck())->analyze([
            $this->template('a.html', '<f:image src="x"/>'),
            $this->template('b.html', '<img src="/logo.svg"/>'),
        ]);

        self::assertFalse($result->isInconsistent());

        $mixed = (new ImageApproachCheck())->analyze([
            $this->template('a.html', '<f:image src="x"/>'),
            $this->template('c.html', '<img src="{file.publicUrl}"/>'),
        ]);
        self::assertTrue($mixed->isInconsistent());
    }

    public function testLinkApproachDetectsDynamicRawAnchor(): void
    {
        $result = (new LinkApproachCheck())->analyze([
            $this->template('a.html', '<f:link.page pageUid="1">p</f:link.page>'),
            $this->template('b.html', '<a href="{url}">x</a>'),
        ]);

        self::assertTrue($result->isInconsistent());

        // A static anchor alone is not a competing approach.
        $static = (new LinkApproachCheck())->analyze([
            $this->template('c.html', '<a href="/about">about</a>'),
        ]);
        self::assertFalse($static->isInconsistent());
    }

    public function testSelectByNameAndWildcard(): void
    {
        $all = ConsistencyRegistry::default();

        self::assertCount(1, ConsistencyRegistry::select($all, ['sliders'], []));
        self::assertCount(count($all) - 1, ConsistencyRegistry::select($all, [], ['icons']));
        self::assertCount(count($all), ConsistencyRegistry::select($all, ['*'], []));
    }

    private function template(string $file, string $source): ParsedTemplate
    {
        return new ParsedTemplate($file, (new TemplateParser())->parse($source));
    }
}
