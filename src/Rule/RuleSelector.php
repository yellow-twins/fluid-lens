<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

/**
 * Narrows a set of rules to the ones the user asked for.
 *
 * {@see $only} keeps just the matching rules (when non-empty); {@see $exclude}
 * then removes matching rules. Patterns are matched against {@see Rule::name()}
 * and may use a trailing `*` wildcard, so `wcag.*` selects every WCAG rule.
 */
final class RuleSelector
{
    /**
     * @param list<Rule>   $rules
     * @param list<string> $only
     * @param list<string> $exclude
     *
     * @return list<Rule>
     */
    public function select(array $rules, array $only, array $exclude): array
    {
        return array_values(array_filter(
            $rules,
            function (Rule $rule) use ($only, $exclude): bool {
                if ($only !== [] && !$this->matchesAny($rule->name(), $only)) {
                    return false;
                }

                return !$this->matchesAny($rule->name(), $exclude);
            },
        ));
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $name, string $pattern): bool
    {
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($name, substr($pattern, 0, -1));
        }

        return $name === $pattern;
    }
}
