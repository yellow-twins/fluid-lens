<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

use YellowTwins\FluidLens\Support\Wildcard;

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
            static function (Rule $rule) use ($only, $exclude): bool {
                if ($only !== [] && !Wildcard::matchesAny($rule->name(), $only)) {
                    return false;
                }

                return !Wildcard::matchesAny($rule->name(), $exclude);
            },
        ));
    }
}
