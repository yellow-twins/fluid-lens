<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Fingerprint;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Fingerprint\SkeletonHasher;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class SkeletonHasherTest extends TestCase
{
    private TemplateParser $parser;
    private SkeletonHasher $hasher;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
        $this->hasher = new SkeletonHasher();
    }

    public function testSameStructureWithDifferentClassesSharesHash(): void
    {
        $a = $this->firstChild('<div class="teaser red"><span>Buy now</span></div>');
        $b = $this->firstChild('<div class="card blue"><span>Read more</span></div>');

        self::assertSame(
            $this->hasher->fingerprint($a)->hash,
            $this->hasher->fingerprint($b)->hash,
        );
    }

    public function testDifferentStructureProducesDifferentHash(): void
    {
        $a = $this->firstChild('<div><span>x</span></div>');
        $b = $this->firstChild('<div><a>x</a></div>');

        self::assertNotSame(
            $this->hasher->fingerprint($a)->hash,
            $this->hasher->fingerprint($b)->hash,
        );
    }

    public function testDifferingAttributeNamesProduceDifferentHash(): void
    {
        $a = $this->firstChild('<f:render partial="Grid"/>');
        $b = $this->firstChild('<f:render section="Grid"/>');

        self::assertNotSame(
            $this->hasher->fingerprint($a)->hash,
            $this->hasher->fingerprint($b)->hash,
        );
    }

    public function testCountsElementsIncludingSelf(): void
    {
        $node = $this->firstChild('<div><span>x</span><a>y</a></div>');

        self::assertSame(3, $this->hasher->fingerprint($node)->elementCount);
    }

    private function firstChild(string $source): \YellowTwins\FluidLens\Parser\Node
    {
        return $this->parser->parse($source)->elementChildren()[0];
    }
}
