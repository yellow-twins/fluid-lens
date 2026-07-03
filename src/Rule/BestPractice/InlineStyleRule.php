<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\BestPractice;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * Inline `style` attributes scatter presentation across templates instead of
 * keeping it in CSS. Advisory.
 */
final class InlineStyleRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'style.inline';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!Attributes::present($element, 'style')) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Notice,
                sprintf('<%s> has an inline style attribute; prefer a CSS class.', $element->name),
                $file,
            ),
        ];
    }
}
