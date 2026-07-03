<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\BestPractice;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * An inline `<svg>` icon repeated across templates is exactly the kind of markup
 * that belongs in a single reusable Icon partial. Advisory.
 */
final class InlineSvgRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'partial.inline-svg';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'svg' || $element->elementChildren() === []) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Notice,
                'Inline <svg>; consider extracting it into a reusable Icon partial.',
                $file,
            ),
        ];
    }
}
