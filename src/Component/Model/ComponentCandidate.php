<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * A suggestion to turn repeated markup or a Partial into a Fluid component,
 * with everything needed to act on it: the proposed name and arguments, the
 * reasons, the caveats, a draft and the call replacing each occurrence.
 */
final class ComponentCandidate
{
    /**
     * @param string                    $id          stable identity, used by the baseline
     * @param string                    $tag         the proposed tag, e.g. "st:teaserHeader"
     * @param list<CandidateSource>     $sources
     * @param list<CandidateOccurrence> $occurrences
     * @param list<ArgumentProposal>    $arguments
     * @param list<string>              $reasons
     * @param list<CandidateNote>       $caveats
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $tag,
        public readonly array $sources,
        public readonly int $elementCount,
        public readonly array $occurrences,
        public readonly array $arguments,
        public readonly ?SlotProposal $slot,
        public readonly array $reasons,
        public readonly array $caveats,
        public readonly CandidateScore $score,
        public readonly ?PartialUsage $partial = null,
        public readonly ?ComponentDraft $draft = null,
        public readonly ?ExistingComponentMatch $existingMatch = null,
    ) {
    }

    public function callSites(): int
    {
        return count($this->occurrences);
    }

    public function fileCount(): int
    {
        return count(array_unique(array_map(
            static fn (CandidateOccurrence $occurrence): string => $occurrence->file,
            $this->occurrences,
        )));
    }

    /**
     * @return list<string>
     */
    public function caveatCodes(): array
    {
        return array_map(static fn (CandidateNote $note): string => $note->code, $this->caveats);
    }
}
