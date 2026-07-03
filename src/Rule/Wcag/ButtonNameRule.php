<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\AccessibleName;

/**
 * A button must expose a discernible name — the icon-only button with just an
 * SVG inside announces nothing to assistive technology.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class ButtonNameRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.button-name';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'button' || AccessibleName::isPresent($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Error,
                'Button has no discernible text. Add visible text or an aria-label.',
                $file,
                'WCAG 4.1.2 (A)',
            ),
        ];
    }
}
