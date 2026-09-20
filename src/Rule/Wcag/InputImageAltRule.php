<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * An `<input type="image">` is an image button and needs `alt` text to name it.
 *
 * WCAG 1.1.1 Non-text Content (Level A).
 */
final class InputImageAltRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.input-image-alt';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'input' || Attributes::value($element, 'type') !== 'image') {
            return [];
        }

        if (Attributes::value($element, 'alt') !== null || Attributes::value($element, 'aria-label') !== null) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Error,
                'Image button (<input type="image">) has no alt text.',
                $file,
                'WCAG 1.1.1 (A)',
            ),
        ];
    }
}
