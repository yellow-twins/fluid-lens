<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\AccessibleName;

/**
 * Every link must expose discernible text, otherwise assistive technology
 * announces nothing useful — the classic icon-only link with just an SVG inside.
 *
 * WCAG 2.4.4 Link Purpose / 4.1.2 Name, Role, Value (Level A).
 */
final class LinkNameRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.link-name';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!$this->isLink($element) || AccessibleName::isPresent($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Error,
                sprintf('<%s> has no discernible text. Add visible text or an aria-label.', $element->name),
                $file,
                'WCAG 2.4.4 (A)',
            ),
        ];
    }

    private function isLink(Node $element): bool
    {
        return $element->name === 'a' || str_contains($element->name, 'link.');
    }
}
