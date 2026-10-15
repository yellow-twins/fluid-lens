<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Index;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Index\ComponentIndex;
use YellowTwins\FluidLens\Tests\Component\TemplateFactory;

final class ComponentIndexTest extends TestCase
{
    private const COLLECTION = 'http://typo3.org/ns/Vendor/Site/Components';

    public function testDerivesComponentNamesFromTheirPath(): void
    {
        self::assertSame('icon', ComponentIndex::nameFromPath('ext/Resources/Private/Components/Icon/Icon.fluid.html'));
        self::assertSame(
            'schema.event',
            ComponentIndex::nameFromPath('ext/Resources/Private/Components/Schema/Event/Event.fluid.html'),
        );
        self::assertSame('teaser', ComponentIndex::nameFromPath('ext/Resources/Private/Components/Teaser.html'));
    }

    public function testIndexesDefinitionsPrefixesAndUsages(): void
    {
        $index = ComponentIndex::build(TemplateFactory::many([
            'ext/Resources/Private/Components/Icon/Icon.fluid.html' => '<svg></svg>',
            'ext/Resources/Private/Templates/A.html' => '<html xmlns:st="' . self::COLLECTION . '">'
                . '<st:icon name="a" /><st:Icon name="b" />{st:icon(name: \'c\')}</html>',
            'ext/Resources/Private/Templates/B.html' => "{namespace st=Vendor\\Site\\Components}<st:icon name=\"d\" />",
        ]));

        self::assertSame('st', $index->dominantPrefix());
        self::assertSame('Vendor\\Site\\Components', $index->namespaceOf('st'));
        self::assertSame('ext/Resources/Private/Components', $index->collectionDirectory());
        self::assertCount(1, $index->components());
        self::assertSame(4, $index->components()[0]->usages);
        self::assertTrue($index->hasComponent('Icon'));
        self::assertTrue($index->isComponentTag('st:icon'));
        self::assertFalse($index->isComponentTag('f:render'));
    }

    public function testWithoutComponentsThereIsNoPrefix(): void
    {
        $index = ComponentIndex::build(TemplateFactory::many(['a/Templates/A.html' => '<div></div>']));

        self::assertNull($index->dominantPrefix());
        self::assertNull($index->collectionDirectory());
    }

    public function testConfiguredPrefixesCount(): void
    {
        $index = ComponentIndex::build(TemplateFactory::many(['a/Templates/A.html' => '<my:card />']), ['my']);

        self::assertSame('my', $index->dominantPrefix());
    }
}
