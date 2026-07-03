<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Elements;

/**
 * A `<fieldset>` grouping form controls needs a `<legend>` naming the group.
 *
 * WCAG 1.3.1 Info and Relationships / 3.3.2 Labels or Instructions (Level A).
 */
final class FieldsetLegendRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.fieldset-legend';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'fieldset' || $this->hasLegend($element)) {
            return [];
        }

        return [
            $this->finding($element, Severity::Warning, '<fieldset> has no <legend>.', $file, 'WCAG 1.3.1 (A)'),
        ];
    }

    private function hasLegend(Node $element): bool
    {
        foreach (Elements::all($element) as $descendant) {
            if ($descendant->name === 'legend') {
                return true;
            }
        }

        return false;
    }
}
