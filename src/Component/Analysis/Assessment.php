<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Model\ExclusionReason;

/**
 * Whether a structure can become a component: blockers keep it a Partial,
 * caveats are changes the conversion needs.
 */
final class Assessment
{
    /**
     * @param list<ExclusionReason> $blockers
     * @param list<CandidateNote>   $caveats
     */
    public function __construct(
        public readonly array $blockers,
        public readonly array $caveats,
    ) {
    }

    public function isBlocked(): bool
    {
        return $this->blockers !== [];
    }
}
