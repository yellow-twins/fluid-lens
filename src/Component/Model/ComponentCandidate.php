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

    /**
     * A copy with a different identity, explanation, Partial reference or
     * score — used when copied markup turns out to be the body of several
     * Partials that can be merged.
     *
     * @param list<string> $reasons
     */
    public function rebrand(string $id, array $reasons, PartialUsage $partial, CandidateScore $score): self
    {
        return new self(
            $id,
            $this->name,
            $this->tag,
            $this->sources,
            $this->elementCount,
            $this->occurrences,
            $this->arguments,
            $this->slot,
            $reasons,
            $this->caveats,
            $score,
            $partial,
            $this->draft,
            $this->existingMatch,
        );
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
