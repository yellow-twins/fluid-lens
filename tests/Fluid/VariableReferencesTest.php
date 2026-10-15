<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Fluid;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Fluid\VariableReferences;
use YellowTwins\FluidLens\Fluid\VariableUsage;
use YellowTwins\FluidLens\Parser\TemplateParser;

final class VariableReferencesTest extends TestCase
{
    /**
     * @return array<string, VariableUsage>
     */
    private function variables(string $template): array
    {
        return (new VariableReferences())->in((new TemplateParser())->parse($template));
    }

    public function testFindsRootsAndPaths(): void
    {
        $usages = $this->variables('<a href="{item.url}">{item.title} {settings.listPageUid}</a>');

        self::assertSame(['item', 'settings'], array_keys($usages));
        self::assertSame(['item.title', 'item.url'], $usages['item']->paths);
        self::assertSame(['settings.listPageUid'], $usages['settings']->paths);
    }

    public function testRemovesLocalBindings(): void
    {
        $usages = $this->variables(
            '<f:for each="{items}" as="item" iteration="i">{item.title}{i.index}</f:for>'
            . '<f:variable name="local" value="{other}" />{local}',
        );

        self::assertSame(['items', 'other'], array_keys($usages));
    }

    public function testRecordsTheRoleOfAVariable(): void
    {
        $usages = $this->variables(
            '<f:if condition="{compact}"><f:for each="{items}" as="x">{x}</f:for></f:if>'
            . '<f:for each="{podcast.artists}" as="a">{a}</f:for>',
        );

        self::assertTrue($usages['compact']->hasHint(VariableUsage::HINT_CONDITION));
        self::assertTrue($usages['items']->hasHint(VariableUsage::HINT_EACH));
        // Looping over a property says nothing about the variable itself.
        self::assertFalse($usages['podcast']->hasHint(VariableUsage::HINT_EACH));
        self::assertTrue($usages['podcast']->isAccessedByPath());
    }

    public function testReadsInlineViewHelperArgumentsButNotKeysOrNames(): void
    {
        $usages = $this->variables(
            "<span>{f:if(condition: spaced, then: 'a', else: label)}"
            . "{f:translate(key: 'x', arguments: {0: count})}</span>",
        );

        self::assertSame(['count', 'label', 'spaced'], array_keys($usages));
        self::assertTrue($usages['spaced']->hasHint(VariableUsage::HINT_CONDITION));
    }

    public function testIgnoresLiteralsCommentsAndNamespaceDeclarations(): void
    {
        $usages = $this->variables(
            "{namespace st=Vendor\\Ext\\Components}<f:comment>{hidden}</f:comment>"
            . "<f:if condition=\"{a} == 'b' && true\">{c as integer}</f:if>",
        );

        self::assertSame(['a', 'c'], array_keys($usages));
    }
}
