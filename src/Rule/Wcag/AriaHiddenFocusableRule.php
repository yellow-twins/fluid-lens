<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Focusable;

/**
 * An element hidden from assistive technology with `aria-hidden="true"` must not
 * stay keyboard-focusable, or a screen-reader user can land on an element that
 * announces nothing.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class AriaHiddenFocusableRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.aria-hidden-focusable';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->attribute('aria-hidden') !== 'true' || !Focusable::isFocusable($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('<%s> is aria-hidden but still focusable; remove it from the tab order.', $element->name),
                $file,
                'WCAG 4.1.2 (A)',
            ),
        ];
    }
}
