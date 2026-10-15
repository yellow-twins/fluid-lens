<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Index;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Index\PartialIndex;
use YellowTwins\FluidLens\Component\Index\ResolutionStatus;
use YellowTwins\FluidLens\Fluid\RenderCall;
use YellowTwins\FluidLens\Tests\Component\TemplateFactory;

final class PartialIndexTest extends TestCase
{
    private PartialIndex $index;

    protected function setUp(): void
    {
        $this->index = PartialIndex::build(TemplateFactory::many([
            'events/Resources/Private/Partials/Teaser/Header.html' => '<div></div>',
            'shop/Resources/Private/Partials/Teaser/Header.html' => '<div></div>',
            'shop/Resources/Private/Partials/Price.fluid.html' => '<span></span>',
            'shop/Resources/Private/Components/Card/Card.fluid.html' => '<div></div>',
        ]));
    }

    private function call(string $file, string $partial): RenderCall
    {
        return new RenderCall($file, 1, $partial, null, null, false, false);
    }

    public function testIndexesPartialsButNotComponents(): void
    {
        self::assertSame(
            ['Price', 'Teaser/Header', 'Teaser/Header'],
            array_map(static fn ($partial): string => $partial->name, $this->index->partials()),
        );
    }

    public function testPrefersThePartialOfTheCallersOwnResources(): void
    {
        $resolution = $this->index->resolve($this->call('shop/Resources/Private/Templates/List.html', 'Teaser/Header'));

        self::assertSame(ResolutionStatus::Resolved, $resolution->status);
        self::assertSame('shop/Resources/Private/Partials/Teaser/Header.html', $resolution->partial?->file());
    }

    public function testFallsBackToTheOnlyPartialOfThatName(): void
    {
        $resolution = $this->index->resolve($this->call('blocks/header/templates/frontend.html', 'Price'));

        self::assertSame(ResolutionStatus::Resolved, $resolution->status);
    }

    public function testNeverGuesses(): void
    {
        $other = 'blocks/header/templates/frontend.html';

        $ambiguous = $this->index->resolve($this->call($other, 'Teaser/Header'));
        self::assertSame(ResolutionStatus::Ambiguous, $ambiguous->status);
        self::assertSame(ResolutionStatus::Missing, $this->index->resolve($this->call($other, 'Mail/Footer'))->status);
        self::assertSame(ResolutionStatus::Dynamic, $this->index->resolve($this->call($other, 'Item/{type}'))->status);
    }
}
