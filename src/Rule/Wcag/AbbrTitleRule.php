<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * An `<abbr>` should carry the expanded form in its `title`, so the abbreviation
 * can be understood.
 *
 * WCAG 3.1.4 Abbreviations (Level AAA).
 */
final class AbbrTitleRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.abbr-title';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'abbr' || Attributes::present($element, 'title')) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Notice,
                '<abbr> has no title with the expanded form.',
                $file,
                'WCAG 3.1.4 (AAA)',
            ),
        ];
    }
}
