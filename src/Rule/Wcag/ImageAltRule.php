<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * Every image needs a text alternative: an `alt` attribute must be present.
 * A decorative image satisfies this with an explicit empty `alt=""`.
 *
 * WCAG 1.1.1 Non-text Content (Level A).
 */
final class ImageAltRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'wcag.img-alt';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!$this->isImage($element) || $element->attribute('alt') !== null) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Error,
                sprintf('<%s> has no alt attribute. Add alt text, or alt="" if it is decorative.', $element->name),
                $file,
                'WCAG 1.1.1 (A)',
            ),
        ];
    }

    private function isImage(Node $element): bool
    {
        return $element->name === 'img' || $element->name === 'f:image';
    }
}
