<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * A positive `tabindex` forces a custom tab order that almost always breaks the
 * natural, logical focus order.
 *
 * WCAG 2.4.3 Focus Order (Level A).
 */
final class PositiveTabindexRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.positive-tabindex';
    }

    protected function inspect(Node $element, string $file): array
    {
        $tabindex = $element->attribute('tabindex');
        if ($tabindex === null || Attributes::isDynamic($tabindex) || !$this->isPositive($tabindex)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('Avoid positive tabindex ("%s"); it disrupts the natural focus order.', $tabindex),
                $file,
                'WCAG 2.4.3 (A)',
            ),
        ];
    }

    private function isPositive(string $value): bool
    {
        return is_numeric($value) && (int) $value > 0;
    }
}
