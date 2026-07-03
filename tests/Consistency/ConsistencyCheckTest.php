<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Consistency;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Consistency\Check\IconSetCheck;
use YellowTwins\FluidLens\Consistency\Check\SliderLibraryCheck;
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
