<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Index\ComponentIndex;
use YellowTwins\FluidLens\Component\Index\PartialFile;
use YellowTwins\FluidLens\Component\Index\PartialIndex;
use YellowTwins\FluidLens\Component\Index\ResolutionStatus;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentReport;
use YellowTwins\FluidLens\Component\Model\KeptPartial;
use YellowTwins\FluidLens\Component\Model\UnresolvedPartial;
use YellowTwins\FluidLens\Detector\CloneDetector;
use YellowTwins\FluidLens\Detector\CloneOccurrence;
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
        $this->clones = $clones ?? new CloneCandidateFactory($scorer);
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

        if ($this->settings->uses(CandidateSource::Partial)) {
            [$kept, $unreferenced, $unresolved, $skippedPartials] = $this->advisePartials(
                $templates,
                $context,
                $deduplicator,
            );
            $skipped += $skippedPartials;
        }

        if ($this->settings->uses(CandidateSource::Clone)) {
            $skipped += $this->adviseClones($templates, $context, $deduplicator);
        }

        [$candidates, $duplicates] = $deduplicator->result();

        return new ComponentReport(
            count($templates),
            $candidates,
            $this->sortKept($kept),
            $components->components(),
            $unreferenced,
            $unresolved,
            $skipped + $duplicates,
        );
    }

    /**
     * @param list<ParsedTemplate> $templates
     *
     * @return array{0: list<KeptPartial>, 1: list<PartialFile>, 2: list<UnresolvedPartial>, 3: int}
     */
    private function advisePartials(array $templates, DraftContext $context, CandidateDeduplicator $deduplicator): array
    {
        $partials = PartialIndex::build($templates);
        [$callsByFile, $unresolved] = $this->resolveCalls($templates, $partials);

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
                $deduplicator->add($result, [$partial->template->tree]);
            }
        }

        return [$kept, $unreferenced, $unresolved, $skipped];
    }

    /**
     * @param list<ParsedTemplate> $templates
     *
     * @return int the number of repeated structures below the thresholds or unsuitable
     */
    private function adviseClones(array $templates, DraftContext $context, CandidateDeduplicator $deduplicator): int
    {
        $byFile = [];
        $scanned = [];
        foreach ($templates as $template) {
            $byFile[$template->file] = $template;
            if (!ComponentIndex::isDefinition($template->file)) {
                $scanned[] = $template;
            }
        }

        $detector = new CloneDetector($this->settings->minElements, $this->settings->minOccurrences);
        $skipped = 0;
        foreach ($detector->detect($scanned) as $group) {
            $files = array_unique(array_map(static fn (CloneOccurrence $o): string => $o->file, $group->occurrences));
            if (!$this->meetsThresholds($group->occurrenceCount(), count($files))) {
                $skipped++;
                continue;
            }

            $candidate = $this->clones->create(
                'clone:' . $group->hash,
                $group->elementCount,
                $group->occurrences,
                $byFile,
                $context,
            );
            if ($candidate === null) {
                $skipped++;
                continue;
            }

            $deduplicator->add(
                $candidate,
                array_map(static fn (CloneOccurrence $occurrence): Node => $occurrence->node, $group->occurrences),
            );
        }

        return $skipped;
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
