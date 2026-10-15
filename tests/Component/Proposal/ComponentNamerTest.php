<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Proposal;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Proposal\ComponentNamer;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

final class ComponentNamerTest extends TestCase
{
    public function testNamesFromThePartialPath(): void
    {
        $namer = new ComponentNamer();

        self::assertSame('TeaserHeader', $namer->fromPartialName('Event/Teaser/Header'));
        self::assertSame('PodcastCard', $namer->fromPartialName('Podcast/Card'));
        self::assertSame('Billing', $namer->fromPartialName('Cart/Confirmation/Address/Billing'));
        self::assertSame('ListItem', $namer->fromPartialName('List/Item'));
    }

    public function testOffersMoreSpecificNamesForClashes(): void
    {
        self::assertSame(
            ['TeaserHeader', 'EventTeaserHeader'],
            (new ComponentNamer())->partialNameOptions('Event/Teaser/Header'),
        );
    }

    public function testNamesCopiedMarkupFromTheSharedClassName(): void
    {
        $namer = new ComponentNamer();
        $roots = [
            new Node(NodeType::Element, 'div', ['class' => 'events-teasers-header mt-4']),
            new Node(NodeType::Element, 'div', ['class' => 'artists-teasers-header']),
        ];

        self::assertSame('TeasersHeader', $namer->fromMarkup($roots));
    }

    public function testFallsBackToTheRoleOfTheRootElement(): void
    {
        $namer = new ComponentNamer();

        self::assertSame('Navigation', $namer->fromMarkup([new Node(NodeType::Element, 'nav', ['class' => 'd-flex'])]));
        self::assertSame('Block', $namer->fromMarkup([new Node(NodeType::Element, 'div')]));
    }

    public function testBuildsTheTag(): void
    {
        self::assertSame('st:teaserHeader', ComponentNamer::tag('st', 'TeaserHeader'));
    }
}
