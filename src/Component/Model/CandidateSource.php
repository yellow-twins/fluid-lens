<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * Which signal suggested a component.
 */
enum CandidateSource: string
{
    /** Identical markup copied into several places. */
    case Clone = 'clone';
    /** Similar markup that differs in parts (those parts become arguments or the slot). */
    case NearDuplicate = 'similar';
    /** A Partial rendered from several places. */
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Clone => 'copied markup',
            self::NearDuplicate => 'similar markup',
            self::Partial => 'partial',
        };
    }
}
