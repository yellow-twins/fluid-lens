<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Fingerprint;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Fingerprint\PqGramProfiler;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class PqGramProfilerTest extends TestCase
{
    private TemplateParser $parser;
    private PqGramProfiler $profiler;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
        $this->profiler = new PqGramProfiler();
    }

    public function testIdenticalStructuresAreFullySimilar(): void
    {
        $a = $this->firstChild('<div><span>x</span><span>y</span></div>');
        $b = $this->firstChild('<div><span>a</span><span>b</span></div>');

        self::assertSame(1.0, $this->similarity($a, $b));
    }

    public function testOneExtraChildScoresHighButBelowOne(): void
    {
        $a = $this->firstChild('<ul><li>1</li><li>2</li><li>3</li></ul>');
        $b = $this->firstChild('<ul><li>1</li><li>2</li><li>3</li><li>4</li></ul>');

        $similarity = $this->similarity($a, $b);

        self::assertGreaterThan(0.7, $similarity);
        self::assertLessThan(1.0, $similarity);
    }

    public function testDifferingAttributeNamesLowersSimilarityBelowOne(): void
    {
        $a = $this->firstChild('<div><f:render partial="A"/></div>');
        $b = $this->firstChild('<div><f:render section="A"/></div>');

        self::assertLessThan(1.0, $this->similarity($a, $b));
    }

    public function testUnrelatedStructuresScoreLow(): void
    {
        $a = $this->firstChild('<div><span>x</span></div>');
        $b = $this->firstChild('<table><thead><tr><th>h</th></tr></thead><tbody><tr><td>c</td></tr></tbody></table>');

        self::assertLessThan(0.5, $this->similarity($a, $b));
    }

    private function similarity(Node $a, Node $b): float
    {
        return $this->profiler->similarity($this->profiler->profile($a), $this->profiler->profile($b));
    }

    private function firstChild(string $source): Node
    {
        return $this->parser->parse($source)->elementChildren()[0];
    }
}
