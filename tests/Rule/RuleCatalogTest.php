<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Tests\Rule;

use PHPUnit\Framework\TestCase;
use YellowTwins\FluidLens\Rule\RuleCatalog;
use YellowTwins\FluidLens\Rule\RuleSet;

final class RuleCatalogTest extends TestCase
{
    public function testEveryDefaultRuleHasADescription(): void
    {
        foreach (RuleSet::default() as $rule) {
            self::assertNotNull(
                RuleCatalog::describe($rule->name()),
                sprintf('Rule "%s" is missing a RuleCatalog description.', $rule->name()),
            );
        }
    }

    public function testCatalogHasNoDescriptionsForMissingRules(): void
    {
        $names = array_map(static fn ($rule): string => $rule->name(), RuleSet::default());

        foreach (array_keys(RuleCatalog::all()) as $documented) {
            self::assertContains(
                $documented,
                $names,
                sprintf('RuleCatalog documents "%s" which is not a registered rule.', $documented),
            );
        }
    }
}
