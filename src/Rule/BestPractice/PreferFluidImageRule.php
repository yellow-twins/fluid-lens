<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\BestPractice;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * In TYPO3, `<f:image>` handles image processing and responsive rendering that a
 * raw `<img>` does not. Advisory.
 */
final class PreferFluidImageRule extends AbstractElementRule
{
    public function name(): string
    {
        return 'image.prefer-fluid';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->name !== 'img') {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Notice,
                'Raw <img>; prefer <f:image> so TYPO3 can process and scale the image.',
                $file,
            ),
        ];
    }
}
