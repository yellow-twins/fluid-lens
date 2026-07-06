<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\FluidUsageCheck;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\FluidSyntax;

/**
 * Partials can be rendered with the tag form `<f:render partial="...">` or the
 * inline form `{f:render(partial: '...')}`. Both do the same thing; picking one
 * keeps templates consistent.
 */
final class RenderStyleCheck extends FluidUsageCheck
{
    public function name(): string
    {
        return 'render-style';
    }

    public function title(): string
    {
        return 'Partial render style (tag vs inline)';
    }

    protected function variantsIn(Node $tree): array
    {
        $variants = [];
        if (FluidSyntax::hasElement($tree, 'f:render')) {
            $variants[] = '<f:render> tag';
        }
        if (FluidSyntax::inlineContains($tree, '{f:render')) {
            $variants[] = '{f:render(...)} inline';
        }

        return $variants;
    }
}
