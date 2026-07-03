<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * A `<meta http-equiv="refresh">` reloads or redirects the page on a timer the
 * user cannot control.
 *
 * WCAG 2.2.1 Timing Adjustable / 3.2.5 Change on Request (Level A / AAA).
 */
final class MetaRefreshRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.meta-refresh';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'meta' || strtolower($element->attribute('http-equiv') ?? '') !== 'refresh') {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                '<meta http-equiv="refresh"> reloads/redirects on a timer the user cannot control.',
                $file,
                'WCAG 2.2.1 (A)',
            ),
        ];
    }
}
