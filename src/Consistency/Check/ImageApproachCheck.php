<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\FluidUsageCheck;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\FluidSyntax;

/**
 * Images can be rendered with `<f:image>`, with `<f:uri.image>` feeding a plain
 * `<img>`, or with a raw `<img>` whose `src` is a Fluid expression (a CMS image
 * that bypasses the image ViewHelpers). Mixing these is worth consolidating.
 * A raw `<img>` with a static `src` is ignored — that is a legitimate asset,
 * not a competing approach.
 */
final class ImageApproachCheck extends FluidUsageCheck
{
    public function name(): string
    {
        return 'image-approach';
    }

    public function title(): string
    {
        return 'Image rendering approach';
    }

    protected function variantsIn(Node $tree): array
    {
        $variants = [];
        if (FluidSyntax::hasElement($tree, 'f:image')) {
            $variants[] = '<f:image>';
        }
        if (FluidSyntax::hasElement($tree, 'f:uri.image')) {
            $variants[] = '<f:uri.image> + <img>';
        }
        if (FluidSyntax::hasDynamicAttribute($tree, 'img', 'src')) {
            $variants[] = 'raw <img> with dynamic src';
        }

        return $variants;
    }
}
