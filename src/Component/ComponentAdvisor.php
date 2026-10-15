<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Index\ComponentIndex;
use YellowTwins\FluidLens\Component\Index\PartialIndex;
use YellowTwins\FluidLens\Component\Index\ResolutionStatus;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\ComponentReport;
use YellowTwins\FluidLens\Component\Model\KeptPartial;
use YellowTwins\FluidLens\Component\Model\UnresolvedPartial;
use YellowTwins\FluidLens\Fluid\RenderCall;
use YellowTwins\FluidLens\Fluid\RenderCallFinder;
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

    public function __construct(
        private readonly AdvisorSettings $settings = new AdvisorSettings(),
        private readonly RenderCallFinder $renderCalls = new RenderCallFinder(),
        ?PartialCandidateFactory $partials = null,
    ) {
        $this->partials = $partials ?? new PartialCandidateFactory(new CandidateScorer($settings->maxArguments));
    }

    /**
     * @param list<ParsedTemplate> $templates
     */
    public function advise(array $templates): ComponentReport
    {
        $components = ComponentIndex::build($templates, $this->settings->prefixes);
        $context = new DraftContext($components);

        $candidates = [];
        $kept = [];
        $unreferenced = [];
        $unresolved = [];
        $skipped = 0;

        if ($this->settings->uses(CandidateSource::Partial)) {
            $partials = PartialIndex::build($templates);
            [$callsByFile, $unresolved] = $this->resolveCalls($templates, $partials);

            foreach ($partials->partials() as $partial) {
                $calls = $callsByFile[$partial->file()] ?? [];
                if ($calls === []) {
                    $unreferenced[] = $partial;
                    continue;
                }

                if (!$this->meetsThresholds($calls)) {
                    $skipped++;
                    continue;
                }

                $result = $this->partials->create($partial, $calls, $context);
                if ($result === null) {
                    $skipped++;
                } elseif ($result instanceof KeptPartial) {
                    $kept[] = $result;
                } else {
                    $candidates[] = $result;
                }
            }
        }

        return new ComponentReport(
            count($templates),
            $this->rank($candidates),
            $this->sortKept($kept),
            $components->components(),
            $unreferenced,
            $unresolved,
            $skipped,
        );
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

    /**
     * @param non-empty-list<RenderCall> $calls
     */
    private function meetsThresholds(array $calls): bool
    {
        $files = array_unique(array_map(static fn (RenderCall $call): string => $call->file, $calls));

        return count($calls) >= $this->settings->minOccurrences && count($files) >= $this->settings->minFiles;
    }

    /**
     * @param list<ComponentCandidate> $candidates
     *
     * @return list<ComponentCandidate>
     */
    private function rank(array $candidates): array
    {
        usort(
            $candidates,
            static fn (ComponentCandidate $a, ComponentCandidate $b): int
                => [$b->score->value, $a->name] <=> [$a->score->value, $b->name],
        );

        return $candidates;
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
