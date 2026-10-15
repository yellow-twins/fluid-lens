<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Index;

/**
 * A render call resolved (or not) to a Partial file.
 */
final class PartialResolution
{
    public function __construct(
        public readonly ResolutionStatus $status,
        public readonly ?PartialFile $partial = null,
    ) {
    }
}
