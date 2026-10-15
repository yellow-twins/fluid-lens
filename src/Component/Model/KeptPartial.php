<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * A frequently rendered Partial that must stay a Partial, and why.
 */
final class KeptPartial
{
    /**
     * @param list<ExclusionReason> $reasons
     */
    public function __construct(
        public readonly string $name,
        public readonly string $file,
        public readonly int $callSites,
        public readonly array $reasons,
    ) {
    }
}
