<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Markup;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Elements;

/**
 * A `<picture>` must contain an `<img>`; the `<source>` elements only offer
 * alternatives, and without the `<img>` fallback nothing is rendered at all.
 */
final class PictureImgRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'markup.picture-img';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'picture' || $this->containsImage($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                '<picture> has no <img> fallback; it will render nothing.',
                $file,
            ),
        ];
    }

    private function containsImage(Node $element): bool
    {
        foreach (Elements::all($element) as $descendant) {
            if ($descendant->name === 'img') {
                return true;
            }
        }

        return false;
    }
}
