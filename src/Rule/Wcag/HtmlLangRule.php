<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * A document's `<html>` element must declare its language.
 *
 * Fluid templates commonly open with an `<html data-namespace-typo3-fluid="true"
 * xmlns:f="...">` wrapper that only declares ViewHelper namespaces and is stripped
 * from the rendered output — that wrapper is not the document root and is skipped.
 *
 * WCAG 3.1.1 Language of Page (Level A).
 */
final class HtmlLangRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.html-lang';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'html' || $element->attribute('lang') !== null) {
            return [];
        }

        if ($this->isFluidNamespaceWrapper($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                'The <html> element has no lang attribute.',
                $file,
                'WCAG 3.1.1 (A)',
            ),
        ];
    }

    private function isFluidNamespaceWrapper(Node $element): bool
    {
        if ($element->attribute('data-namespace-typo3-fluid') !== null) {
            return true;
        }

        foreach (array_keys($element->attributes) as $attribute) {
            if (str_starts_with($attribute, 'xmlns')) {
                return true;
            }
        }

        return false;
    }
}
