<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\FluidUsageCheck;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\FluidSyntax;

/**
 * Labels can be translated with the tag form `<f:translate key="...">` or the
 * inline form `{f:translate(key: '...')}`. Settling on one keeps templates
 * uniform.
 */
final class TranslateStyleCheck extends FluidUsageCheck
{
    public function name(): string
    {
        return 'translate-style';
    }

    public function title(): string
    {
        return 'Translation style (tag vs inline)';
    }

    protected function variantsIn(Node $tree): array
    {
        $variants = [];
        if (FluidSyntax::hasElement($tree, 'f:translate')) {
            $variants[] = '<f:translate> tag';
        }
        if (FluidSyntax::inlineContains($tree, '{f:translate')) {
            $variants[] = '{f:translate(...)} inline';
        }

        return $variants;
    }
}
