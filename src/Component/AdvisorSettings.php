<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Model\CandidateSource;

/**
 * The thresholds and switches of a components run.
 */
final class AdvisorSettings
{
    /**
     * @param int                   $minOccurrences call sites (render calls or copies) a candidate needs
     * @param int                   $minFiles       distinct files those call sites must spread over
     * @param int                   $minElements    minimum size of copied markup
     * @param int                   $maxArguments   above this a suggestion is flagged as too configurable
     * @param float                 $threshold      similarity for similar markup, 0..1
     * @param float                 $minShared      share of values and texts copies must have in common
     * @param list<CandidateSource> $sources        which signals to use
     * @param list<string>          $prefixes       component namespace prefixes besides the detected ones
     */
    public function __construct(
        public readonly int $minOccurrences = 2,
        public readonly int $minFiles = 2,
        public readonly int $minElements = 3,
        public readonly int $maxArguments = 6,
        public readonly float $threshold = 0.8,
        public readonly array $sources = [
            CandidateSource::Partial,
            CandidateSource::Clone,
            CandidateSource::NearDuplicate,
        ],
        public readonly array $prefixes = [],
        public readonly float $minShared = 0.45,
    ) {
    }

    public function uses(CandidateSource $source): bool
    {
        return in_array($source, $this->sources, true);
    }
}
