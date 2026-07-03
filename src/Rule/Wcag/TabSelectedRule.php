<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Roles;

/**
 * A tab in a tablist must expose its selected state with `aria-selected`, or a
 * screen-reader user cannot tell which tab is active.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class TabSelectedRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.tab-selected';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!Roles::has($element, 'tab') || $element->attribute('aria-selected') !== null) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                'role="tab" without aria-selected; the active tab cannot be announced.',
                $file,
                'WCAG 4.1.2 (A)',
            ),
        ];
    }
}
