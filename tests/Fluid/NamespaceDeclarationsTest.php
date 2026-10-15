<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Fluid;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Fluid\NamespaceDeclarations;

final class NamespaceDeclarationsTest extends TestCase
{
    public function testReadsXmlnsAttributes(): void
    {
        $source = '<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"'
            . ' xmlns:st="http://typo3.org/ns/Sachsen/Projectfiles/Components" data-namespace-typo3-fluid="true">';

        self::assertSame(
            ['f' => 'TYPO3\\CMS\\Fluid\\ViewHelpers', 'st' => 'Sachsen\\Projectfiles\\Components'],
            NamespaceDeclarations::fromSource($source),
        );
    }

    public function testReadsInlineDeclarations(): void
    {
        self::assertSame(
            ['st' => 'Sachsen\\Projectfiles\\Components'],
            NamespaceDeclarations::fromSource("{namespace st=Sachsen\\Projectfiles\\Components}\n<div></div>"),
        );
    }

    public function testIgnoresNamespacesOutsideTypo3(): void
    {
        self::assertSame([], NamespaceDeclarations::fromSource('<svg xmlns:xlink="http://www.w3.org/1999/xlink">'));
    }

    public function testBuildsTheUriForANamespace(): void
    {
        self::assertSame(
            'http://typo3.org/ns/Vendor/Ext/Components',
            NamespaceDeclarations::uriFor('Vendor\\Ext\\Components'),
        );
    }
}
