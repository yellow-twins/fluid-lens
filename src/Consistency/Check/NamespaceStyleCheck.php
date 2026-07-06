<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\FluidUsageCheck;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\FluidSyntax;

/**
 * Fluid namespaces can be declared on the `<html>` tag
 * (`data-namespace-typo3-fluid` / `xmlns:*`) or inline with `{namespace ...}`.
 * A codebase reads more easily when it settles on one.
 */
final class NamespaceStyleCheck extends FluidUsageCheck
{
    public function name(): string
    {
        return 'namespace-style';
    }

    public function title(): string
    {
        return 'Fluid namespace declaration style';
    }

    protected function variantsIn(Node $tree): array
    {
        $variants = [];
        if (FluidSyntax::hasAttribute($tree, 'data-namespace-typo3-fluid')) {
            $variants[] = '<html> tag (data-namespace-typo3-fluid)';
        }
        if (FluidSyntax::inlineContains($tree, '{namespace ')) {
            $variants[] = '{namespace ...} inline';
        }

        return $variants;
    }
}
