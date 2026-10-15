<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * An existing component that looks like the suggested one — use it instead of
 * creating another.
 */
final class ExistingComponentMatch
{
    public function __construct(
        public readonly string $tag,
        public readonly string $file,
        public readonly float $similarity,
    ) {
    }
}
