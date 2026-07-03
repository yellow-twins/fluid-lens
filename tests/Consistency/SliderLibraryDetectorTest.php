<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Consistency;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Consistency\SliderLibrary;
use YellowTwins\FluidLens\Consistency\SliderLibraryDetector;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class SliderLibraryDetectorTest extends TestCase
{
    public function testMatchesKnownSignatureClasses(): void
    {
        self::assertSame('Swiper', SliderLibrary::match('swiper-slide'));
        self::assertSame('Slick', SliderLibrary::match('slick'));
        self::assertSame('Glide', SliderLibrary::match('glide__slide'));
        self::assertNull(SliderLibrary::match('container'));
    }

    public function testReportsASingleLibraryAcrossTemplates(): void
    {
        $templates = [
            $this->template('a.html', '<div class="swiper"><div class="swiper-slide">1</div></div>'),
            $this->template('b.html', '<div class="swiper">x</div>'),
        ];

        $usages = (new SliderLibraryDetector())->detect($templates);

        self::assertCount(1, $usages);
        self::assertSame('Swiper', $usages[0]->library);
        self::assertSame(2, $usages[0]->fileCount());
    }

    public function testDetectsMixedLibraries(): void
    {
        $templates = [
            $this->template('a.html', '<div class="swiper">1</div>'),
            $this->template('b.html', '<div class="slick-slider">2</div>'),
        ];

        $usages = (new SliderLibraryDetector())->detect($templates);

        $names = array_map(static fn ($usage): string => $usage->library, $usages);
        self::assertContains('Swiper', $names);
        self::assertContains('Slick', $names);
    }

    private function template(string $file, string $source): ParsedTemplate
    {
        return new ParsedTemplate($file, (new TemplateParser())->parse($source));
    }
}
