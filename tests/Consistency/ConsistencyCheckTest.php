<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Consistency;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Consistency\Check\AnimationCheck;
use YellowTwins\FluidLens\Consistency\Check\CookieConsentCheck;
use YellowTwins\FluidLens\Consistency\Check\CssFrameworkCheck;
use YellowTwins\FluidLens\Consistency\Check\GridLayoutCheck;
use YellowTwins\FluidLens\Consistency\Check\IconSetCheck;
use YellowTwins\FluidLens\Consistency\Check\JsFrameworkCheck;
use YellowTwins\FluidLens\Consistency\Check\LazyLoadCheck;
use YellowTwins\FluidLens\Consistency\Check\LightboxCheck;
use YellowTwins\FluidLens\Consistency\Check\MapLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\SliderLibraryCheck;
use YellowTwins\FluidLens\Consistency\Check\TooltipLibraryCheck;
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
