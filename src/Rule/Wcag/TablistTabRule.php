<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Support\Roles;

/**
 * A `role="tablist"` must actually contain tabs; without any `role="tab"` the
 * widget is announced as a tablist that a user cannot operate.
 *
 * WCAG 1.3.1 Info and Relationships (Level A).
 */
final class TablistTabRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.tablist-tab';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!Roles::has($element, 'tablist') || $this->containsTab($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                'role="tablist" contains no role="tab".',
                $file,
                'WCAG 1.3.1 (A)',
            ),
        ];
    }

    private function containsTab(Node $element): bool
    {
        foreach (Elements::all($element) as $descendant) {
            if ($descendant !== $element && Roles::has($descendant, 'tab')) {
                return true;
            }
        }

        return false;
    }
}
