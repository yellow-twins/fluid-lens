<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Rule\RuleSelector;
use YellowTwins\FluidLens\Rule\RuleSet;

final class RuleSelectorTest extends TestCase
{
    public function testOnlyKeepsNamedRules(): void
    {
        $selected = (new RuleSelector())->select(RuleSet::default(), ['wcag.img-alt'], []);

        self::assertCount(1, $selected);
        self::assertSame('wcag.img-alt', $selected[0]->name());
    }

    public function testExcludeRemovesNamedRules(): void
    {
        $all = RuleSet::default();
        $selected = (new RuleSelector())->select($all, [], ['style.inline']);

        $names = array_map(static fn ($rule): string => $rule->name(), $selected);
        self::assertNotContains('style.inline', $names);
        self::assertCount(count($all) - 1, $selected);
    }

    public function testEmptyFiltersKeepEverything(): void
    {
        self::assertCount(count(RuleSet::default()), (new RuleSelector())->select(RuleSet::default(), [], []));
    }
}
