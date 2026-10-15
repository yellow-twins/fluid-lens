<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Draft;

use YellowTwins\FluidLens\Fluid\SourceCasing;

/**
 * What one write needs throughout: the edits to apply and the source spelling.
 */
final class MarkupWriting
{
    public function __construct(
        public readonly Substitutions $substitutions,
        public readonly SourceCasing $casing,
    ) {
    }
}
