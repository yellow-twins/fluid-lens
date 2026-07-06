<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which tooltip/popover library the project uses, by the attribute or
 * class that wires it up. Only signatures specific to one library are listed —
 * Bootstrap's generic `data-bs-toggle` is deliberately left out, because it
 * cannot be told apart from a dropdown or modal without the runtime value.
 */
final class TooltipLibraryCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'tooltip';
    }

    public function title(): string
    {
        return 'Tooltip libraries';
    }

    protected function catalog(): array
    {
        return [
            'Tippy.js' => ['tippy', 'data-tippy-content'],
            'Foundation' => ['data-tooltip'],
            'hint.css' => ['hint', 'data-hint'],
            'microtip' => ['data-microtip-position'],
        ];
    }
}
