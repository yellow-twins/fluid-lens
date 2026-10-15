<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Analysis;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Analysis\DiffPoint;
use YellowTwins\FluidLens\Component\Analysis\OccurrenceAligner;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class OccurrenceAlignerTest extends TestCase
{
    /**
     * @return list<DiffPoint>|null
     */
    private function align(string $first, string ...$others): ?array
    {
        $parser = new TemplateParser();
        $root = static fn (string $copy): Node => $parser->parse($copy)->elementChildren()[0];

        return (new OccurrenceAligner())->align([$root($first), ...array_values(array_map($root, $others))]);
    }

    public function testFindsAttributeClassAndTextDifferences(): void
    {
        $points = $this->align(
            '<a class="link yellow" href="{a.url}"><span>More</span></a>',
            '<a class="link purple" href="{b.url}"><span>Less</span></a>',
        );

        self::assertNotNull($points);
        self::assertSame(
            [[DiffPoint::CLASS_TOKENS, 'class'], [DiffPoint::ATTRIBUTE, 'href'], [DiffPoint::TEXT, null]],
            array_map(static fn (DiffPoint $p): array => [$p->kind, $p->attribute], $points),
        );
        self::assertSame(['More', 'Less'], $points[2]->values);
    }

    public function testAMissingAttributeIsNull(): void
    {
        $points = $this->align('<div><img src="a.png" title="A"></div>', '<div><img src="a.png"></div>');

        self::assertNotNull($points);
        self::assertSame(['A', null], $points[0]->values);
        self::assertTrue($points[0]->isMissingSomewhere());
    }

    public function testLocalVariablesAreRenamesNotDifferences(): void
    {
        $points = $this->align(
            '<ul><f:for each="{artists}" as="artist"><li>{artist.name}</li></f:for></ul>',
            '<ul><f:for each="{residents}" as="resident"><li>{resident.name}</li></f:for></ul>',
        );

        self::assertNotNull($points);
        self::assertCount(1, $points);
        self::assertSame('each', $points[0]->attribute);
    }

    public function testIdenticalCopiesHaveNoDifferences(): void
    {
        self::assertSame([], $this->align('<p><b>x</b></p>', '<p><b>x</b></p>'));
    }

    public function testCopiesThatDoNotLineUpAreRejected(): void
    {
        self::assertNull($this->align('<p><b>x</b></p>', '<p><i>x</i></p>'));
    }
}
