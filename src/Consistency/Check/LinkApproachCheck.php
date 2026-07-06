<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\FluidUsageCheck;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\FluidSyntax;

/**
 * Internal links can go through a link ViewHelper (`<f:link.page>`,
 * `<f:link.typolink>`, …) or a raw `<a>` whose `href` is a Fluid expression
 * (a generated URL that bypasses the link ViewHelpers). Mixing the two is worth
 * consolidating. A raw `<a>` with a static `href` (external link, anchor) is
 * ignored — that is not a competing approach.
 */
final class LinkApproachCheck extends FluidUsageCheck
{
    public function name(): string
    {
        return 'link-approach';
    }

    public function title(): string
    {
        return 'Link rendering approach';
    }

    protected function variantsIn(Node $tree): array
    {
        $variants = [];
        if (FluidSyntax::hasElementWithPrefix($tree, 'f:link.')) {
            $variants[] = 'Fluid link ViewHelper';
        }
        if (FluidSyntax::hasDynamicAttribute($tree, 'a', 'href')) {
            $variants[] = 'raw <a> with dynamic href';
        }

        return $variants;
    }
}
