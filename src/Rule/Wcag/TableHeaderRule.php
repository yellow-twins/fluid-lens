<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Elements;

/**
 * A data table needs header cells (`<th>`) so its rows and columns can be
 * understood. Tables explicitly marked as presentational are exempt.
 *
 * WCAG 1.3.1 Info and Relationships (Level A).
 */
final class TableHeaderRule extends AbstractElementRule
{
    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'table' || $this->isPresentational($element)) {
            return [];
        }

        $descendants = Elements::all($element);
        if (!$this->contains($descendants, 'tr') || $this->contains($descendants, 'th')) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                'Data table has no <th> header cells.',
                $file,
                'WCAG 1.3.1 (A)',
            ),
        ];
    }

    public function name(): string
    {
        return 'wcag.table-header';
    }

    private function isPresentational(Node $element): bool
    {
        return in_array($element->attribute('role'), ['presentation', 'none'], true);
    }

    /**
     * @param list<Node> $elements
     */
    private function contains(array $elements, string $name): bool
    {
        foreach ($elements as $element) {
            if ($element->name === $name) {
                return true;
            }
        }

        return false;
    }
}
