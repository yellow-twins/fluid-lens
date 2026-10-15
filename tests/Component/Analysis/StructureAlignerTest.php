<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Analysis;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Analysis\DiffPoint;
use YellowTwins\FluidLens\Component\Analysis\StructureAligner;
use YellowTwins\FluidLens\Component\Analysis\StructureAlignment;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class StructureAlignerTest extends TestCase
{
    private function align(string $first, string ...$others): ?StructureAlignment
    {
        $parser = new TemplateParser();
        $root = static fn (string $copy): Node => $parser->parse($copy)->elementChildren()[0];

        return (new StructureAligner())->align([$root($first), ...array_values(array_map($root, $others))]);
    }

    public function testAnElementSomeCopiesOmitBecomesOptional(): void
    {
        $alignment = $this->align(
            '<div class="head"><h2>A</h2><a href="x">all<i class="icon"></i></a></div>',
            '<div class="head"><h2>A</h2><a href="x">all</a></div>',
        );

        self::assertNotNull($alignment);
        self::assertSame([], $alignment->points);
        self::assertCount(1, $alignment->presence);
        self::assertSame('i', $alignment->presence[0]['node']->name);
        self::assertSame([true, false], $alignment->presence[0]['present']);
        self::assertNull($alignment->slot);
    }

    public function testDivergingContentBecomesTheSlot(): void
    {
        $alignment = $this->align(
            '<div class="head"><h2 class="title"><f:translate key="a" /></h2><a href="x">all</a></div>',
            '<div class="head"><h2 class="title"><f:if condition="{more}">More</f:if></h2><a href="x">all</a></div>',
        );

        self::assertNotNull($alignment);
        self::assertSame('h2', $alignment->slot?->name);
        self::assertSame('h2', $alignment->slotNodes[1]?->name);
    }

    public function testAttributesAnElementGainsAreDifferences(): void
    {
        $alignment = $this->align(
            '<p><st:icon name="arrow" /><b>x</b></p>',
            '<p><st:icon name="arrow" color="purple" /><b>x</b></p>',
        );

        self::assertNotNull($alignment);
        self::assertSame(
            [[DiffPoint::ATTRIBUTE, 'color', [null, 'purple']]],
            array_map(static fn (DiffPoint $p): array => [$p->kind, $p->attribute, $p->values], $alignment->points),
        );
    }

    public function testCopiesDivergingRightBelowTheRootAreRejected(): void
    {
        self::assertNull($this->align(
            '<div><h2>A</h2><p>text</p></div>',
            '<div><ul><li>x</li></ul><span>B</span></div>',
        ));
        self::assertNull($this->align('<div><b>x</b></div>', '<section><b>x</b></section>'));
    }
}
