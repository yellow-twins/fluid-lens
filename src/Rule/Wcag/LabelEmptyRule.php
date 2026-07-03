<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\AccessibleName;

/**
 * A `<label>` with no text names nothing.
 *
 * WCAG 3.3.2 Labels or Instructions (Level A).
 */
final class LabelEmptyRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.label-empty';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'label' || AccessibleName::isPresent($element)) {
            return [];
        }

        return [
            $this->finding($element, Severity::Warning, '<label> has no text.', $file, 'WCAG 3.3.2 (A)'),
        ];
    }
}
