<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * A `<summary>` is the toggle of a `<details>` disclosure; outside a `<details>`
 * it is inert and exposes no control.
 *
 * WCAG 1.3.1 Info and Relationships (Level A).
 */
final class SummaryDetailsRule extends AbstractElementRule
{
    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'summary' || $element->parent()?->name === 'details') {
            return [];
        }

        return [
            $this->finding($element, Severity::Warning, '<summary> outside a <details>.', $file, 'WCAG 1.3.1 (A)'),
        ];
    }

    public function name(): string
    {
        return 'wcag.summary-details';
    }
}
