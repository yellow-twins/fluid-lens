<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Component\Index\ExistingComponent;
use YellowTwins\FluidLens\Component\Index\PartialFile;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Model\CandidateOccurrence;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\ComponentReport;
use YellowTwins\FluidLens\Component\Model\ExclusionReason;
use YellowTwins\FluidLens\Component\Model\KeptPartial;
use YellowTwins\FluidLens\Component\Model\UnresolvedPartial;

/**
 * Serialises component suggestions to JSON, complete with drafts and the call
 * for every occurrence, so a tool or an AI assistant can carry them out.
 */
final class JsonComponentReporter
{
    public function render(ComponentReport $report): string
    {
        $payload = [
            'filesScanned' => $report->filesScanned,
            'candidateCount' => count($report->candidates),
            'candidates' => array_map(fn (ComponentCandidate $c): array => $this->candidate($c), $report->candidates),
            'keptAsPartials' => array_map(fn (KeptPartial $k): array => $this->kept($k), $report->keptPartials),
            'existingComponents' => array_map(
                static fn (ExistingComponent $component): array => [
                    'name' => $component->name,
                    'file' => $component->definition->file,
                    'usages' => $component->usages,
                ],
                $report->existingComponents,
            ),
            'unreferencedPartials' => array_map(
                static fn (PartialFile $partial): array => ['name' => $partial->name, 'file' => $partial->file()],
                $report->unreferencedPartials,
            ),
            'unresolvedPartials' => array_map(
                static fn (UnresolvedPartial $partial): array => [
                    'name' => $partial->name,
                    'reason' => $partial->reason,
                    'callers' => $partial->callers,
                ],
                $report->unresolvedPartials,
            ),
            'skippedLowPayoff' => $report->skippedLowPayoff,
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function candidate(ComponentCandidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'tag' => $candidate->tag,
            'score' => $candidate->score->value,
            'priority' => $candidate->score->priority(),
            'scoreFactors' => $candidate->score->factors,
            'sources' => array_map(static fn (CandidateSource $source): string => $source->value, $candidate->sources),
            'elementCount' => $candidate->elementCount,
            'callSites' => $candidate->callSites(),
            'files' => $candidate->fileCount(),
            'reasons' => $candidate->reasons,
            'caveats' => array_map(
                static fn (CandidateNote $note): array => ['code' => $note->code, 'message' => $note->message],
                $candidate->caveats,
            ),
            'arguments' => array_map(fn (ArgumentProposal $a): array => $this->argument($a), $candidate->arguments),
            'slot' => $candidate->slot === null ? null : [
                'location' => $candidate->slot->location,
                'variants' => $candidate->slot->variants,
            ],
            'draft' => $candidate->draft === null ? null : [
                'path' => $candidate->draft->path,
                'source' => $candidate->draft->source,
            ],
            'occurrences' => array_map(
                static fn (CandidateOccurrence $occurrence): array => [
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'call' => $occurrence->call,
                ],
                $candidate->occurrences,
            ),
            'partial' => $candidate->partial === null ? null : [
                'names' => $candidate->partial->names,
                'files' => $candidate->partial->files,
            ],
            'existingMatch' => $candidate->existingMatch === null ? null : [
                'tag' => $candidate->existingMatch->tag,
                'file' => $candidate->existingMatch->file,
                'similarity' => round($candidate->existingMatch->similarity, 2),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function argument(ArgumentProposal $argument): array
    {
        return [
            'name' => $argument->name,
            'type' => $argument->typeName(),
            'optional' => $argument->optional,
            'default' => $argument->default,
            'origin' => $argument->origin->value,
            'observedValues' => $argument->observedValues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kept(KeptPartial $kept): array
    {
        return [
            'name' => $kept->name,
            'file' => $kept->file,
            'callSites' => $kept->callSites,
            'reasons' => array_map(
                static fn (ExclusionReason $reason): array
                    => ['code' => $reason->value, 'message' => $reason->message()],
                $kept->reasons,
            ),
        ];
    }
}
