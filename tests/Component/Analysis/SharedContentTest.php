<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Analysis;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Analysis\DiffPoint;
use YellowTwins\FluidLens\Component\Analysis\SharedContent;
use YellowTwins\FluidLens\Component\Analysis\StructureAlignment;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class SharedContentTest extends TestCase
{
    public function testCountsAttributesAndTexts(): void
    {
        $root = (new TemplateParser())->parse('<a href="x" class="c">Go <b>now</b></a>')->elementChildren()[0];

        self::assertSame(4, (new SharedContent())->slots($root));
    }

    public function testClassesCountByTheirTokens(): void
    {
        $root = (new TemplateParser())->parse('<a href="x" class="link yellow">Go</a>')->elementChildren()[0];
        $alignment = new StructureAlignment([
            new DiffPoint(DiffPoint::CLASS_TOKENS, $root, ['link yellow', 'link purple'], 'class'),
        ]);

        // 3 slots, one of which differs by two of its three class names.
        self::assertEqualsWithDelta(1 - (2 / 3) / 3, (new SharedContent())->share($root, $alignment), 0.001);
    }

    public function testOptionalElementsAndTheSlotCountAsDifferences(): void
    {
        $tree = (new TemplateParser())->parse('<div class="a"><h2 class="t">x</h2><i class="i"></i></div>');
        $root = $tree->elementChildren()[0];
        [$heading, $icon] = $root->elementChildren();

        $alignment = new StructureAlignment([], [['node' => $icon, 'present' => [true, false]]], $heading, []);

        // 4 slots: the optional icon and the heading's text differ.
        self::assertEqualsWithDelta(0.5, (new SharedContent())->share($root, $alignment), 0.001);
    }
}
