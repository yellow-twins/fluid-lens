<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\Elements;

/**
 * Interactive controls must not be nested — a link inside a button, or a button
 * inside a link, has undefined keyboard behaviour and confuses assistive tech.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class NestedInteractiveRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.nested-interactive';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!$this->isInteractive($element)) {
            return [];
        }

        foreach (Elements::all($element) as $descendant) {
            if ($descendant !== $element && $this->isInteractive($descendant)) {
                return [
                    $this->finding(
                        $element,
                        Severity::Warning,
                        sprintf('<%s> nests an interactive <%s>.', $element->name, $descendant->name),
                        $file,
                        'WCAG 4.1.2 (A)',
                    ),
                ];
            }
        }

        return [];
    }

    private function isInteractive(Node $element): bool
    {
        if ($element->name === 'a') {
            return Attributes::present($element, 'href');
        }

        return $element->name === 'button';
    }
}
