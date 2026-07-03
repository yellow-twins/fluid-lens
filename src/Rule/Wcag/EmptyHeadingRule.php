<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\AccessibleName;

/**
 * A heading must have text — an empty heading breaks the document outline that
 * screen-reader users navigate by.
 *
 * WCAG 1.3.1 Info and Relationships / 2.4.6 Headings and Labels (Level A/AA).
 */
final class EmptyHeadingRule extends AbstractElementRule
{
    protected function inspect(Node $element, string $file): array
    {
        if (preg_match('/^h[1-6]$/', $element->name) !== 1 || AccessibleName::isPresent($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('<%s> is empty; a heading must have text.', $element->name),
                $file,
                'WCAG 1.3.1 (A)',
            ),
        ];
    }

    public function name(): string
    {
        return 'wcag.empty-heading';
    }
}
