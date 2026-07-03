<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Runs a set of rules over parsed templates and collects their findings, sorted
 * into a stable order (by file, then line).
 */
final class Linter
{
    /**
     * @param list<Rule> $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    public static function withDefaultRules(): self
    {
        return new self(RuleSet::default());
    }

    /**
     * @param list<ParsedTemplate> $templates
     *
     * @return list<Finding>
     */
    public function lint(array $templates): array
    {
        $findings = [];
        foreach ($templates as $template) {
            foreach ($this->rules as $rule) {
                foreach ($rule->check($template) as $finding) {
                    $findings[] = $finding;
                }
            }
        }

        usort(
            $findings,
            static fn (Finding $a, Finding $b): int => [$a->file, $a->line] <=> [$b->file, $b->line],
        );

        return $findings;
    }
}
