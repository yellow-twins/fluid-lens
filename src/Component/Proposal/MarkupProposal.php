<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Proposal;

use YellowTwins\FluidLens\Component\Draft\Substitutions;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\CandidateNote;

/**
 * The arguments proposed for copied markup, the edits turning the first copy
 * into the component body and, per copy, the values its call passes.
 */
final class MarkupProposal
{
    /**
     * @param list<ArgumentProposal>       $arguments
     * @param list<array<string, string>> $callValues one map of argument => value per copy
     * @param list<CandidateNote>          $caveats
     */
    public function __construct(
        public readonly array $arguments,
        public readonly Substitutions $substitutions,
        public readonly array $callValues,
        public readonly array $caveats = [],
    ) {
    }
}
