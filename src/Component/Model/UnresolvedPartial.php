<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * A rendered Partial name that could not be attributed to a scanned file.
 */
final class UnresolvedPartial
{
    /**
     * @param list<string> $callers "file:line" of each render call
     */
    public function __construct(
        public readonly string $name,
        public readonly string $reason,
        public readonly array $callers,
    ) {
    }
}
