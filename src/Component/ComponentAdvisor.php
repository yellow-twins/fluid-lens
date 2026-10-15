<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Index\ComponentIndex;
use YellowTwins\FluidLens\Component\Index\PartialFile;
use YellowTwins\FluidLens\Component\Index\PartialIndex;
use YellowTwins\FluidLens\Component\Index\ResolutionStatus;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\ComponentReport;
use YellowTwins\FluidLens\Component\Model\KeptPartial;
use YellowTwins\FluidLens\Component\Model\UnresolvedPartial;
use YellowTwins\FluidLens\Detector\CloneDetector;
use YellowTwins\FluidLens\Detector\CloneOccurrence;
use YellowTwins\FluidLens\Detector\NearDuplicateDetector;
use YellowTwins\FluidLens\Detector\NearDuplicateMember;
use YellowTwins\FluidLens\Fingerprint\SkeletonHasher;
use YellowTwins\FluidLens\Fluid\RenderCall;
use YellowTwins\FluidLens\Fluid\RenderCallFinder;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Suggests which parts of a template base should become Fluid components.
 *
 * It combines the signals — Partials rendered from several places, markup
 * copied into several places — filters out what has to stay a Partial and
 * ranks the rest by how much a component would save.
 */
final class ComponentAdvisor
{
    private readonly PartialCandidateFactory $partials;
    private readonly CloneCandidateFactory $clones;

    public function __construct(
        private readonly AdvisorSettings $settings = new AdvisorSettings(),
        private readonly RenderCallFinder $renderCalls = new RenderCallFinder(),
        ?PartialCandidateFactory $partials = null,
        ?CloneCandidateFactory $clones = null,
    ) {
        $scorer = new CandidateScorer($settings->maxArguments);
        $this->partials = $partials ?? new PartialCandidateFactory($scorer);
        $this->clones = $clones ?? new CloneCandidateFactory($settings->minShared, $scorer);
    }

    /**
     * @param list<ParsedTemplate> $templates
     */
    public function advise(array $templates): ComponentReport
    {
        $components = ComponentIndex::build($templates, $this->settings->prefixes);
        $context = new DraftContext($components);
        $deduplicator = new CandidateDeduplicator();

        $kept = [];
        $unreferenced = [];
        $unresolved = [];
        $skipped = 0;

        $partials = PartialIndex::build($templates);
        [$callsByFile, $unresolvedCalls] = $this->resolveCalls($templates, $partials);

        if ($this->settings->uses(CandidateSource::Partial)) {
            [$kept, $unreferenced, $skippedPartials] = $this->advisePartials(
                $partials,
                $callsByFile,
                $context,
                $deduplicator,
            );
            $unresolved = $unresolvedCalls;
            $skipped += $skippedPartials;
        }

        $merger = new PartialMerger($partials, $callsByFile);
        if ($this->settings->uses(CandidateSource::Clone)) {
            $skipped += $this->adviseClones($templates, $context, $deduplicator, $merger);
        }

        if ($this->settings->uses(CandidateSource::NearDuplicate)) {
            $skipped += $this->adviseSimilar($templates, $context, $deduplicator, $merger);
        }

        [$candidates, $duplicates] = $deduplicator->result();
        [$candidates, $superseded] = $this->withoutMergedPartials($candidates);

        return new ComponentReport(
            count($templates),
            $candidates,
            $this->sortKept($kept),
            $components->components(),
            $unreferenced,
            $unresolved,
            $skipped + $duplicates + $superseded,
        );
    }

    /**
     * A suggestion to merge several Partials replaces the suggestions for each
     * of those Partials on its own.
     *
     * @param list<ComponentCandidate> $candidates
     *
     * @return array{0: list<ComponentCandidate>, 1: int}
     */
    private function withoutMergedPartials(array $candidates): array
    {
        $merged = [];
        foreach ($candidates as $candidate) {
            if (str_starts_with($candidate->id, 'merge:') && $candidate->partial !== null) {
                foreach ($candidate->partial->files as $file) {
                    $merged['partial:' . $file] = true;
                }
            }
        }

        $kept = array_values(array_filter(
            $candidates,
            static fn (ComponentCandidate $candidate): bool => !isset($merged[$candidate->id]),
        ));

        return [$kept, count($candidates) - count($kept)];
    }

    /**
     * @param array<string, non-empty-list<RenderCall>> $callsByFile
     *
     * @return array{0: list<KeptPartial>, 1: list<PartialFile>, 2: int}
     */
    private function advisePartials(
        PartialIndex $partials,
        array $callsByFile,
        DraftContext $context,
        CandidateDeduplicator $deduplicator,
    ): array {
        $kept = [];
        $unreferenced = [];
        $skipped = 0;
        foreach ($partials->partials() as $partial) {
            $calls = $callsByFile[$partial->file()] ?? [];
            if ($calls === []) {
                $unreferenced[] = $partial;
                continue;
            }

            $files = array_unique(array_map(static fn (RenderCall $call): string => $call->file, $calls));
            if (!$this->meetsThresholds(count($calls), count($files))) {
                $skipped++;
                continue;
            }

            $result = $this->partials->create($partial, $calls, $context);
            if ($result === null) {
                $skipped++;
            } elseif ($result instanceof KeptPartial) {
                $kept[] = $result;
            } else {
                $deduplicator->add($result, PartialCandidateFactory::bodyElements($partial->template->tree));
            }
        }

        return [$kept, $unreferenced, $skipped];
    }

    /**
     * @param list<ParsedTemplate> $templates
     *
     * @return int the number of repeated structures below the thresholds or unsuitable
     */
    private function adviseClones(
        array $templates,
        DraftContext $context,
        CandidateDeduplicator $deduplicator,
        PartialMerger $merger,
    ): int {
        $detector = new CloneDetector($this->settings->minElements, $this->settings->minOccurrences);
        $skipped = 0;
        foreach ($detector->detect($this->scannable($templates)) as $group) {
            $added = $this->addMarkupCandidate(
                'clone:' . $group->hash,
                CandidateSource::Clone,
                $group->elementCount,
                $group->occurrences,
                $templates,
                $context,
                $deduplicator,
                $merger,
            );
            $skipped += $added ? 0 : 1;
        }

        return $skipped;
    }

    /**
     * @param list<ParsedTemplate> $templates
     *
     * @return int the number of similar structures below the thresholds or unsuitable
     */
    private function adviseSimilar(
        array $templates,
        DraftContext $context,
        CandidateDeduplicator $deduplicator,
        PartialMerger $merger,
    ): int {
        $detector = new NearDuplicateDetector($this->settings->threshold, max(4, $this->settings->minElements));
        $hasher = new SkeletonHasher();
        $skipped = 0;
        foreach ($detector->detect($this->scannable($templates)) as $cluster) {
            $members = $cluster->members;
            usort(
                $members,
                static fn (NearDuplicateMember $a, NearDuplicateMember $b): int
                    => $b->occurrenceCount() <=> $a->occurrenceCount(),
            );

            $occurrences = [];
            $hashes = [];
            $elements = 0;
            foreach ($members as $member) {
                array_push($occurrences, ...$member->occurrences);
                $hashes[] = $hasher->fingerprint($member->representative)->hash;
                $elements = max($elements, $member->elementCount);
            }
            sort($hashes);

            $added = $occurrences !== [] && $this->addMarkupCandidate(
                'similar:' . sha1(implode(',', $hashes)),
                CandidateSource::NearDuplicate,
                $elements,
                $occurrences,
                $templates,
                $context,
                $deduplicator,
                $merger,
            );
            $skipped += $added ? 0 : 1;
        }

        return $skipped;
    }

    /**
     * @param non-empty-list<CloneOccurrence> $occurrences
     * @param list<ParsedTemplate>            $templates
     */
    private function addMarkupCandidate(
        string $id,
        CandidateSource $source,
        int $elementCount,
        array $occurrences,
        array $templates,
        DraftContext $context,
        CandidateDeduplicator $deduplicator,
        PartialMerger $merger,
    ): bool {
        $files = array_unique(array_map(static fn (CloneOccurrence $o): string => $o->file, $occurrences));
        if (!$this->meetsThresholds(count($occurrences), count($files))) {
            return false;
        }

        $byFile = [];
        foreach ($templates as $template) {
            $byFile[$template->file] = $template;
        }

        $candidate = $this->clones->create($id, $source, $elementCount, $occurrences, $byFile, $context);
        if ($candidate === null) {
            return false;
        }

        $deduplicator->add(
            $merger->merge($candidate, $occurrences, new CandidateScorer($this->settings->maxArguments)),
            array_map(static fn (CloneOccurrence $occurrence): Node => $occurrence->node, $occurrences),
        );

        return true;
    }

    /**
     * Templates whose markup can be suggested: everything but the component
     * definitions themselves.
     *
     * @param list<ParsedTemplate> $templates
     *
     * @return list<ParsedTemplate>
     */
    private function scannable(array $templates): array
    {
        return array_values(array_filter(
            $templates,
            static fn (ParsedTemplate $template): bool => !ComponentIndex::isDefinition($template->file),
        ));
    }

    /**
     * @param list<ParsedTemplate> $templates
     *
     * @return array{0: array<string, non-empty-list<RenderCall>>, 1: list<UnresolvedPartial>}
     */
    private function resolveCalls(array $templates, PartialIndex $partials): array
    {
        $byFile = [];
        /** @var array<string, array{reason: string, callers: list<string>}> $unresolved */
        $unresolved = [];

        foreach ($templates as $template) {
            foreach ($this->renderCalls->in($template) as $call) {
                if (!$call->isPartialCall()) {
                    continue;
                }

                $resolution = $partials->resolve($call);
                if ($resolution->partial !== null) {
                    $byFile[$resolution->partial->file()][] = $call;
                    continue;
                }

                $key = (string) $call->partial;
                $unresolved[$key] ??= ['reason' => $resolution->status->value, 'callers' => []];
                $unresolved[$key]['callers'][] = sprintf('%s:%d', $call->file, $call->line);
            }
        }

        ksort($unresolved);
        $list = [];
        foreach ($unresolved as $name => $data) {
            if ($data['reason'] !== ResolutionStatus::Resolved->value) {
                $list[] = new UnresolvedPartial((string) $name, $data['reason'], $data['callers']);
            }
        }

        return [$byFile, $list];
    }

    private function meetsThresholds(int $callSites, int $files): bool
    {
        return $callSites >= $this->settings->minOccurrences && $files >= $this->settings->minFiles;
    }

    /**
     * @param list<KeptPartial> $kept
     *
     * @return list<KeptPartial>
     */
    private function sortKept(array $kept): array
    {
        usort(
            $kept,
            static fn (KeptPartial $a, KeptPartial $b): int => [$b->callSites, $a->name] <=> [$a->callSites, $b->name],
        );

        return $kept;
    }
}
