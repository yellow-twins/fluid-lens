<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

/**
 * Narrows a set of rules to the ones the user asked for.
 *
 * {@see $only} keeps just the named rules (when non-empty); {@see $exclude} then
 * removes named rules. Both are matched against {@see Rule::name()}.
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
                if ($only !== [] && !in_array($rule->name(), $only, true)) {
                    return false;
                }

                return !in_array($rule->name(), $exclude, true);
            },
        ));
    }
}
