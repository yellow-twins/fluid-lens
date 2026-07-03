<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Detector;

use YellowTwins\FluidLens\Parser\Node;

/**
 * One appearance of a duplicated structure: where it lives and the subtree itself
 * (kept so the reporter can render a preview of the representative occurrence).
 */
final class CloneOccurrence
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly Node $node,
    ) {
    }
}
