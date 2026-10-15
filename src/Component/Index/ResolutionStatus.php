<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Index;

/**
 * The outcome of resolving a render call to a Partial file.
 */
enum ResolutionStatus: string
{
    case Resolved = 'resolved';
    /** Several scanned Partials share the name and none is closer to the caller. */
    case Ambiguous = 'ambiguous';
    /** No scanned Partial has the name — rendered from elsewhere (vendor, TypoScript paths). */
    case Missing = 'missing';
    /** The name is built from variables. */
    case Dynamic = 'dynamic';
}
