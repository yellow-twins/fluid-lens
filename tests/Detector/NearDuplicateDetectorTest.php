<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Detector;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Detector\NearDuplicateDetector;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

final class NearDuplicateDetectorTest extends TestCase
{
    private TemplateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
    }

    public function testClustersStructuresThatDifferByASingleElement(): void
    {
        // Two cards with the same shape, one carrying an extra caption element.
        $cardA = '<div class="card"><div class="media"><f:image src="a"/></div><h3>t</h3></div>';
        $cardB = '<div class="card"><div class="media"><f:image src="b"/></div><h3>t</h3><p>c</p></div>';
        $templates = [
            new ParsedTemplate('a.html', $this->parser->parse($cardA)),
            new ParsedTemplate('b.html', $this->parser->parse($cardB)),
        ];

        $clusters = (new NearDuplicateDetector(threshold: 0.6, minElements: 4))->detect($templates);

        self::assertCount(1, $clusters);
        self::assertSame(2, $clusters[0]->memberCount());
        self::assertGreaterThan(0.6, $clusters[0]->similarity);
        self::assertLessThan(1.0, $clusters[0]->similarity);
    }

    public function testDoesNotClusterIdenticalStructures(): void
    {
        // Identical structures are exact clones (a different concern); they are
        // collapsed into one variant and therefore never form a near-duplicate.
        $card = '<div class="card"><div class="media"><f:image src="a"/></div><h3>t</h3></div>';
        $templates = [
            new ParsedTemplate('a.html', $this->parser->parse($card)),
            new ParsedTemplate('b.html', $this->parser->parse($card)),
        ];

        self::assertSame([], (new NearDuplicateDetector(minElements: 4))->detect($templates));
    }

    public function testDoesNotClusterUnrelatedStructures(): void
    {
        $templates = [
            new ParsedTemplate('a.html', $this->parser->parse('<div><span>x</span><span>y</span><span>z</span></div>')),
            new ParsedTemplate('b.html', $this->parser->parse('<nav><ul><li>1</li><li>2</li><li>3</li></ul></nav>')),
        ];

        self::assertSame([], (new NearDuplicateDetector(threshold: 0.8, minElements: 4))->detect($templates));
    }
}
