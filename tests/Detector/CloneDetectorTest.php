<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Detector;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Detector\CloneDetector;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class CloneDetectorTest extends TestCase
{
    private TemplateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
    }

    public function testDetectsIdenticalStructureAcrossTemplates(): void
    {
        $structure = '<div class="list"><f:for each="{items}" as="i"><f:render partial="Row"/></f:for></div>';
        $templates = [
            new ParsedTemplate('a.html', $this->parser->parse('<section>' . $structure . '</section>')),
            new ParsedTemplate('b.html', $this->parser->parse('<article>' . $structure . '</article>')),
        ];

        $groups = (new CloneDetector(minElements: 3))->detect($templates);

        self::assertCount(1, $groups);
        self::assertSame(2, $groups[0]->occurrenceCount());
        self::assertSame(['a.html', 'b.html'], [
            $groups[0]->occurrences[0]->file,
            $groups[0]->occurrences[1]->file,
        ]);
    }

    public function testIgnoresStructuresBelowThresholds(): void
    {
        // Occurs only once, and only two elements: below both thresholds.
        $templates = [
            new ParsedTemplate('a.html', $this->parser->parse('<div><span>x</span></div>')),
        ];

        self::assertSame([], (new CloneDetector())->detect($templates));
    }

    public function testSuppressesGroupsFullyContainedInALargerGroup(): void
    {
        // The whole card (4 elements) repeats twice; its inner block (3 elements)
        // also repeats twice — but only inside the cards, so only the larger card
        // is reported and the fully-contained inner group is suppressed.
        $card = '<div class="card"><div class="inner"><span>a</span><span>b</span></div></div>';
        $templates = [
            new ParsedTemplate('a.html', $this->parser->parse('<main>' . $card . $card . '</main>')),
        ];

        $groups = (new CloneDetector(minElements: 3))->detect($templates);

        self::assertCount(1, $groups);
        self::assertSame('div', $groups[0]->representative()->name);
        self::assertSame('card', $groups[0]->representative()->attribute('class'));
    }
}
