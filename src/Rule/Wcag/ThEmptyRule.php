<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\AccessibleName;

/**
 * A table header cell with no text names nothing, leaving the column or row it
 * heads unlabelled for screen-reader users.
 *
 * WCAG 1.3.1 Info and Relationships (Level A).
 */
final class ThEmptyRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.th-empty';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'th' || AccessibleName::isPresent($element)) {
            return [];
        }

        return [
            $this->finding($element, Severity::Warning, 'Empty <th> header cell.', $file, 'WCAG 1.3.1 (A)'),
        ];
    }
}
