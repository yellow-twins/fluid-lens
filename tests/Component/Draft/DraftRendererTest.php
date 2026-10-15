<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Component\Draft;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Component\Draft\DraftRenderer;
use YellowTwins\FluidLens\Component\Draft\ExampleCallRenderer;
use YellowTwins\FluidLens\Component\Model\ArgumentOrigin;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\ArgumentType;

final class DraftRendererTest extends TestCase
{
    public function testRendersAComponentFileInTheUsualLayout(): void
    {
        $draft = (new DraftRenderer())->render(
            'TeaserHeader',
            [
                new ArgumentProposal('title', ArgumentType::String, false, null, ArgumentOrigin::Text),
                new ArgumentProposal('color', ArgumentType::String, true, 'purple', ArgumentOrigin::ClassTokens),
                new ArgumentProposal(
                    'count',
                    ArgumentType::Int,
                    false,
                    null,
                    ArgumentOrigin::Declared,
                    declaredType: 'integer',
                ),
            ],
            '<h2 class="{color}">{title}</h2><st:icon name="arrow" />',
            ['TeaserHeader: suggested by fluid-lens.'],
            ['st' => 'Vendor\\Site\\Components'],
            'ext/Resources/Private/Components',
        );

        self::assertSame('ext/Resources/Private/Components/TeaserHeader/TeaserHeader.fluid.html', $draft->path);
        self::assertSame(
            <<<'FLUID'
            <html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
                  xmlns:st="http://typo3.org/ns/Vendor/Site/Components"
                  data-namespace-typo3-fluid="true">
            <f:comment>
                TeaserHeader: suggested by fluid-lens.
            </f:comment>
            <f:argument name="title" type="string" />
            <f:argument name="color" type="string" optional="{true}" default="purple" />
            <f:argument name="count" type="integer" />

            <h2 class="{color}">{title}</h2><st:icon name="arrow" />
            </html>

            FLUID,
            $draft->source,
        );
    }

    public function testMarksAnUnknownNamespace(): void
    {
        $draft = (new DraftRenderer())->render('Card', [], '<my:badge />', [], [], null);

        self::assertStringContainsString('xmlns:my="TODO: namespace of my"', $draft->source);
        self::assertSame('Resources/Private/Components/Card/Card.fluid.html', $draft->path);
    }

    public function testRendersTheReplacingCall(): void
    {
        $renderer = new ExampleCallRenderer();

        self::assertSame('<st:card title="A &quot;b&quot;" />', $renderer->render('st:card', ['title' => 'A "b"']));
        self::assertSame(
            "<st:card>\n    <f:translate key=\"x\" />\n</st:card>",
            $renderer->render('st:card', [], '<f:translate key="x" />'),
        );
    }
}
