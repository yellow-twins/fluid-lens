<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Analysis\ComponentSuitability;
use YellowTwins\FluidLens\Component\Analysis\OccurrenceAligner;
use YellowTwins\FluidLens\Component\Draft\DraftRenderer;
use YellowTwins\FluidLens\Component\Draft\ExampleCallRenderer;
use YellowTwins\FluidLens\Component\Draft\FluidMarkupWriter;
use YellowTwins\FluidLens\Component\Model\ArgumentOrigin;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\CandidateOccurrence;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Proposal\ArgumentExtractor;
use YellowTwins\FluidLens\Component\Proposal\ArgumentNamer;
use YellowTwins\FluidLens\Component\Proposal\ArgumentTypeInferrer;
use YellowTwins\FluidLens\Component\Proposal\ComponentNamer;
use YellowTwins\FluidLens\Component\Proposal\MarkupProposal;
use YellowTwins\FluidLens\Component\Proposal\SettingsArguments;
use YellowTwins\FluidLens\Detector\CloneOccurrence;
use YellowTwins\FluidLens\Fluid\NamespaceDeclarations;
use YellowTwins\FluidLens\Fluid\SourceCasing;
use YellowTwins\FluidLens\Fluid\VariableReferences;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Turns markup copied into several places into a component suggestion.
 *
 * The copies are lined up node by node; whatever differs between them becomes
 * an argument, and whatever every copy reads from its own surroundings (a
 * variable, a setting) becomes one too, because the component will not see it.
 */
final class CloneCandidateFactory
{
    /**
     * At least this share of the attribute values and texts must be the same in
     * every copy. Below it the copies only share their shape, which is a
     * coincidence of markup, not a component.
     */
    private const MIN_SHARED = 0.5;

    /**
     * Plain HTML elements a copy needs: Fluid control flow on its own, or a
     * wrapper around render calls, is not component material.
     */
    private const MIN_HTML_ELEMENTS = 2;

    public function __construct(
        private readonly CandidateScorer $scorer = new CandidateScorer(),
        private readonly ComponentSuitability $suitability = new ComponentSuitability(),
        private readonly OccurrenceAligner $aligner = new OccurrenceAligner(),
        private readonly ArgumentExtractor $extractor = new ArgumentExtractor(),
        private readonly VariableReferences $variables = new VariableReferences(),
        private readonly ArgumentTypeInferrer $types = new ArgumentTypeInferrer(),
        private readonly SettingsArguments $settings = new SettingsArguments(),
        private readonly ComponentNamer $namer = new ComponentNamer(),
        private readonly FluidMarkupWriter $writer = new FluidMarkupWriter(),
        private readonly DraftRenderer $drafts = new DraftRenderer(),
        private readonly ExampleCallRenderer $calls = new ExampleCallRenderer(),
        private readonly TemplateParser $parser = new TemplateParser(),
    ) {
    }

    /**
     * @param non-empty-list<CloneOccurrence> $occurrences the copies, the first one is drafted from
     * @param array<string, ParsedTemplate>   $templates   by file
     *
     * @return ComponentCandidate|null null when the copies cannot become a component
     */
    public function create(
        string $id,
        int $elementCount,
        array $occurrences,
        array $templates,
        DraftContext $context,
    ): ?ComponentCandidate {
        $first = $occurrences[0];
        $template = $templates[$first->file] ?? null;
        if ($template === null || $context->components->isComponentTag($first->node->name)) {
            return null;
        }

        $assessment = $this->suitability->assess($first->node, $template->tree, $template->file);
        if ($assessment->isBlocked()) {
            return null;
        }

        if ($this->htmlElements($first->node) < self::MIN_HTML_ELEMENTS) {
            return null;
        }

        $roots = array_map(static fn (CloneOccurrence $occurrence): Node => $occurrence->node, $occurrences);
        $points = $this->aligner->align($roots);
        if ($points === null) {
            return null;
        }

        $shared = 1 - count($points) / max(1, $this->valueSlots($first->node));
        if ($shared < self::MIN_SHARED) {
            return null;
        }

        $casing = SourceCasing::fromSource($template->source);
        $proposal = $this->extractor->extract(
            $points,
            count($occurrences),
            $casing,
            array_keys($this->variables->in($first->node)),
        );
        [$proposal, $markup] = $this->completeWithFreeVariables($proposal, $first->node, $casing);

        $name = $context->names->claimFirst([$this->namer->fromMarkup($roots)]);
        $tag = $context->tag($name);
        $arguments = $proposal->arguments;

        if ($this->scorer->isTooConfigurable(count($arguments))) {
            return null;
        }

        $caveats = [...$assessment->caveats, ...$proposal->caveats];
        $collectionCaveat = $context->collectionCaveat();
        if ($collectionCaveat !== null) {
            $caveats[] = $collectionCaveat;
        }

        $candidateOccurrences = [];
        foreach ($occurrences as $index => $occurrence) {
            $candidateOccurrences[] = new CandidateOccurrence(
                $occurrence->file,
                $occurrence->line,
                $this->calls->render($tag, $proposal->callValues[$index] ?? []),
            );
        }
        $files = count(array_unique(array_map(static fn (CloneOccurrence $o): string => $o->file, $occurrences)));

        return new ComponentCandidate(
            id: $id,
            name: $name,
            tag: $tag,
            sources: [CandidateSource::Clone],
            elementCount: $elementCount,
            occurrences: $candidateOccurrences,
            arguments: $arguments,
            slot: null,
            reasons: $this->reasons(count($occurrences), $files, $arguments, $shared),
            caveats: $caveats,
            score: $this->scorer->score(
                count($occurrences),
                $elementCount,
                $files,
                count($arguments),
                ['shared content' => round($shared, 2)],
            ),
            draft: $this->drafts->render(
                $name,
                $arguments,
                $markup,
                [
                    sprintf('%s: suggested by fluid-lens from markup copied into %d files.', $name, $files),
                    'Review the arguments and the markup before use.',
                ],
                NamespaceDeclarations::fromSource($template->source) + $context->collectionNamespace(),
                $context->components->collectionDirectory(),
            ),
        );
    }

    /**
     * Writes the component body and adds an argument for every variable it
     * still reads from its surroundings — the same in every copy, so each call
     * simply passes it on.
     *
     * @return array{0: MarkupProposal, 1: string}
     */
    private function completeWithFreeVariables(MarkupProposal $proposal, Node $root, SourceCasing $casing): array
    {
        $substitutions = $proposal->substitutions;
        $markup = $this->writer->write([$root], $substitutions, $casing);

        $namer = new ArgumentNamer();
        foreach ($proposal->arguments as $argument) {
            $namer->claim($argument->name);
        }

        $arguments = $proposal->arguments;
        $passed = [];
        foreach ($this->variables->in($this->parser->parse($markup)) as $variable => $usage) {
            if ($namer->isTaken($variable) || $variable === '_all') {
                continue;
            }

            if ($variable === 'settings') {
                foreach ($this->settings->propose($usage, $namer, $substitutions) as $name => $argument) {
                    $arguments[] = $argument;
                    $passed[$name] = $argument->observedValues[0] ?? '{settings}';
                }
                continue;
            }

            $name = $namer->claim($variable);
            $arguments[] = new ArgumentProposal(
                $name,
                $this->types->infer([], $usage),
                false,
                null,
                ArgumentOrigin::Variable,
                ['{' . $variable . '}'],
            );
            $passed[$name] = '{' . $variable . '}';
        }

        if ($passed === []) {
            return [$proposal, $markup];
        }

        $callValues = array_map(static fn (array $values): array => $values + $passed, $proposal->callValues);
        $completed = new MarkupProposal($arguments, $substitutions, $callValues, $proposal->caveats);

        return [$completed, $this->writer->write([$root], $substitutions, $casing)];
    }

    /**
     * @param list<ArgumentProposal> $arguments
     *
     * @return list<string>
     */
    private function reasons(int $copies, int $files, array $arguments, float $shared): array
    {
        $reasons = [sprintf(
            'the same markup appears %d× in %d file(s); %d%% of its values and texts are identical in every copy',
            $copies,
            $files,
            (int) round($shared * 100),
        )];

        $differences = array_map(
            static fn (ArgumentProposal $a): string => sprintf('%s (%s)', $a->name, $a->origin->value),
            array_values(array_filter(
                $arguments,
                static fn (ArgumentProposal $argument): bool => $argument->origin !== ArgumentOrigin::Variable
                    && $argument->origin !== ArgumentOrigin::Setting,
            )),
        );
        $reasons[] = $differences === []
            ? 'the copies are identical'
            : 'the copies differ in: ' . implode(', ', $differences);

        return $reasons;
    }

    /**
     * Elements that are plain HTML, not ViewHelpers or component calls.
     */
    private function htmlElements(Node $root): int
    {
        $count = 0;
        foreach (Elements::all($root) as $element) {
            if (!str_contains($element->name, ':')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Attribute values and texts — the places where copies can differ.
     */
    private function valueSlots(Node $root): int
    {
        $slots = 0;
        foreach (Elements::all($root) as $element) {
            $slots += count($element->attributes);
            foreach ($element->children() as $child) {
                if ($child->type === NodeType::Text) {
                    $slots++;
                }
            }
        }

        return $slots;
    }
}
