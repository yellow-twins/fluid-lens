<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\BestPractice;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * A link opening in a new tab should carry `rel="noopener"` so the new page
 * cannot take control of the opener window (tab-nabbing). Advisory.
 */
final class TargetBlankRelRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'link.target-blank-rel';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'a' || $element->attribute('target') !== '_blank') {
            return [];
        }

        if (str_contains(strtolower($element->attribute('rel') ?? ''), 'noopener')) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Notice,
                'target="_blank" without rel="noopener"; add it to prevent tab-nabbing.',
                $file,
            ),
        ];
    }
}
