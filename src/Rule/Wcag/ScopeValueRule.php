<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * A table cell's `scope` must be "col", "row", "colgroup" or "rowgroup".
 *
 * WCAG 1.3.1 Info and Relationships (Level A).
 */
final class ScopeValueRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.scope-value';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'th' && $element->name !== 'td') {
            return [];
        }

        $scope = $element->attribute('scope');
        if (
            $scope === null || Attributes::isDynamic($scope)
            || in_array(strtolower($scope), ['col', 'row', 'colgroup', 'rowgroup'], true)
        ) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('Invalid scope value "%s"; use col, row, colgroup or rowgroup.', $scope),
                $file,
                'WCAG 1.3.1 (A)',
            ),
        ];
    }
}
