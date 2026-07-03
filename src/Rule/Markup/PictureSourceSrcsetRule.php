<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Markup;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * A `<source>` inside a `<picture>` selects an image via `srcset` (not `src`);
 * without it the source contributes nothing. Sources in `<video>`/`<audio>`,
 * which legitimately use `src`, are left alone.
 */
final class PictureSourceSrcsetRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'markup.source-srcset';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'source' || !$this->isInsidePicture($element)) {
            return [];
        }

        if (Attributes::present($element, 'srcset')) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                '<source> in <picture> has no srcset attribute.',
                $file,
            ),
        ];
    }

    private function isInsidePicture(Node $element): bool
    {
        return $element->parent()?->name === 'picture';
    }
}
