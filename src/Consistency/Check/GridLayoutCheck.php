<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency\Check;

use YellowTwins\FluidLens\Consistency\SignatureCheck;

/**
 * Detects which JavaScript grid/masonry layout library the project uses. These
 * all solve the same problem, so a project should settle on one.
 */
final class GridLayoutCheck extends SignatureCheck
{
    public function name(): string
    {
        return 'grid';
    }

    public function title(): string
    {
        return 'Grid / masonry libraries';
    }

    protected function catalog(): array
    {
        return [
            'Isotope' => ['isotope'],
            'Masonry' => ['masonry'],
            'Packery' => ['packery'],
            'Muuri' => ['muuri'],
        ];
    }
}
