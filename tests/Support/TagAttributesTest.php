<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Support;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Support\TagAttributes;

final class TagAttributesTest extends TestCase
{
    public function testAriaArgumentExpandsToAriaAttributes(): void
    {
        $element = $this->firstElement("<f:link.action aria=\"{label: 'Home'}\">x</f:link.action>");

        self::assertSame('Home', TagAttributes::value($element, 'aria-label'));
    }

    public function testDataArgumentExpandsToDataAttributes(): void
    {
        $element = $this->firstElement("<f:link.page data=\"{controller: 'modal'}\">x</f:link.page>");

        self::assertSame('modal', TagAttributes::value($element, 'data-controller'));
    }

    public function testAdditionalAttributesKeepTheirNames(): void
    {
        // The HTML5 tokenizer lowercases the argument name to "additionalattributes".
        $element = $this->firstElement("<f:form.textfield additionalAttributes=\"{'aria-label': 'Search'}\"/>");

        self::assertSame('Search', TagAttributes::value($element, 'aria-label'));
    }

    public function testAnExplicitAttributeWinsOverTheArrayForm(): void
    {
        $element = $this->firstElement("<a aria-label=\"Explicit\" aria=\"{label: 'Array'}\">x</a>");

        self::assertSame('Explicit', TagAttributes::value($element, 'aria-label'));
    }

    public function testPlainAttributesAreReturnedUnchanged(): void
    {
        $element = $this->firstElement('<a href="/x" class="card">y</a>');

        self::assertSame(['href' => '/x', 'class' => 'card'], TagAttributes::resolve($element));
    }

    public function testOpaqueArgumentDetection(): void
    {
        self::assertTrue(TagAttributes::hasOpaqueArgument($this->firstElement('<a aria="{attrs}">x</a>')));
        self::assertFalse(
            TagAttributes::hasOpaqueArgument($this->firstElement("<a aria=\"{label: 'Home'}\">x</a>")),
        );
        // An <object data="…"> is a plain attribute, not an unreadable argument.
        self::assertFalse(TagAttributes::hasOpaqueArgument($this->firstElement('<object data="movie.swf"></object>')));
    }

    private function firstElement(string $source): Node
    {
        return (new TemplateParser())->parse($source)->elementChildren()[0];
    }
}
