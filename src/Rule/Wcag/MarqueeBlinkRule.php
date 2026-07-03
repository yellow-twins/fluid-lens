<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * The `<marquee>` and `<blink>` elements move or flash content with no way to
 * stop it — obsolete and inaccessible.
 *
 * WCAG 2.2.2 Pause, Stop, Hide (Level A).
 */
final class MarqueeBlinkRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.marquee-blink';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'marquee' && $element->name !== 'blink') {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('<%s> moves content with no way to stop it; remove it.', $element->name),
                $file,
                'WCAG 2.2.2 (A)',
            ),
        ];
    }
}
