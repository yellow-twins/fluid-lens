<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Parser;

/**
 * The location of a node within its source template, used to point the user at
 * the exact line a finding refers to.
 */
final class SourceRange
{
    public function __construct(
        public readonly int $startLine,
        public readonly ?int $endLine = null,
    ) {
    }
}
