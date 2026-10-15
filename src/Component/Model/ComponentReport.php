<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

use YellowTwins\FluidLens\Component\Index\ExistingComponent;
use YellowTwins\FluidLens\Component\Index\PartialFile;

/**
 * The outcome of a components run.
 */
final class ComponentReport
{
    /**
     * @param list<ComponentCandidate> $candidates           ranked, best first
     * @param list<KeptPartial>        $keptPartials         rendered often, but must stay Partials
     * @param list<ExistingComponent>  $existingComponents
     * @param list<PartialFile>        $unreferencedPartials no render call in the scanned paths
     * @param list<UnresolvedPartial>  $unresolvedPartials
     * @param int                      $skippedLowPayoff     repetitions below the thresholds
     */
    public function __construct(
        public readonly int $filesScanned,
        public readonly array $candidates,
        public readonly array $keptPartials = [],
        public readonly array $existingComponents = [],
        public readonly array $unreferencedPartials = [],
        public readonly array $unresolvedPartials = [],
        public readonly int $skippedLowPayoff = 0,
    ) {
    }

    /**
     * @param list<ComponentCandidate> $candidates
     */
    public function withCandidates(array $candidates): self
    {
        return new self(
            $this->filesScanned,
            $candidates,
            $this->keptPartials,
            $this->existingComponents,
            $this->unreferencedPartials,
            $this->unresolvedPartials,
            $this->skippedLowPayoff,
        );
    }
}
