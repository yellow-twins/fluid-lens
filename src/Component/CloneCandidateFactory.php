<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Analysis\ComponentSuitability;
use YellowTwins\FluidLens\Component\Analysis\OccurrenceAligner;
use YellowTwins\FluidLens\Component\Analysis\SharedContent;
use YellowTwins\FluidLens\Component\Analysis\StructureAligner;
use YellowTwins\FluidLens\Component\Analysis\StructureAlignment;
use YellowTwins\FluidLens\Component\Draft\DraftRenderer;
use YellowTwins\FluidLens\Component\Draft\ExampleCallRenderer;
use YellowTwins\FluidLens\Component\Draft\FluidMarkupWriter;
use YellowTwins\FluidLens\Component\Draft\Substitutions;
use YellowTwins\FluidLens\Component\Model\ArgumentOrigin;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\CandidateOccurrence;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ArgumentType;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\SlotProposal;
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
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Parser\TemplateParser;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Turns markup copied into several places — identical or similar — into a
 * component suggestion.
 *
 * The copies are lined up node by node; whatever differs between them becomes
 * an argument, an element only some copies have becomes optional, a region
 * that differs too much becomes the slot, and whatever every copy reads from
 * its own surroundings (a variable, a setting) becomes an argument too, because
 * the component will not see it.
 */
final class CloneCandidateFactory
{
    /**
     * Plain HTML elements a copy needs: Fluid control flow on its own, or a
     * wrapper around render calls, is not component material.
     */
    private const MIN_HTML_ELEMENTS = 2;

    /**
     * @param float $minShared the share of values and texts every copy must have in
     *                         common; below it the copies only share their shape
     */
    public function __construct(
        private readonly float $minShared = 0.45,
        private readonly CandidateScorer $scorer = new CandidateScorer(),
        private readonly ComponentSuitability $suitability = new ComponentSuitability(),
        private readonly OccurrenceAligner $aligner = new OccurrenceAligner(),
        private readonly StructureAligner $structures = new StructureAligner(),
        private readonly SharedContent $sharedContent = new SharedContent(),
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
     * @param CandidateSource                 $source      exact copies (Clone) or similar ones (NearDuplicate)
     * @param non-empty-list<CloneOccurrence> $occurrences the copies, the first one is drafted from
     * @param array<string, ParsedTemplate>   $templates   by file
     *
     * @return ComponentCandidate|null null when the copies cannot become a component
     */
    public function create(
        string $id,
        CandidateSource $source,
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
        if ($assessment->isBlocked() || $this->htmlElements($first->node) < self::MIN_HTML_ELEMENTS) {
            return null;
        }

        $roots = array_map(static fn (CloneOccurrence $occurrence): Node => $occurrence->node, $occurrences);
        $alignment = $this->alignment($source, $roots);
        if ($alignment === null) {
            return null;
        }

        $shared = $this->sharedContent->share($first->node, $alignment);
        if ($shared < $this->minShared) {
            return null;
        }

        $casing = SourceCasing::fromSource($template->source);
        $proposal = $this->extractor->extract(
            $alignment->points,
            count($occurrences),
            $casing,
            array_keys($this->variables->in($first->node)),
        );
        $proposal = $this->withOptionalElements($proposal, $alignment);
        $slotContents = $this->withSlot($proposal, $alignment, $occurrences, $templates);
        [$proposal, $markup] = $this->completeWithFreeVariables($proposal, $first->node, $casing);

        $arguments = $proposal->arguments;
        if ($this->scorer->isTooConfigurable(count($arguments))) {
            return null;
        }

        $name = $context->names->claimFirst([$this->namer->fromMarkup($roots)]);
        $tag = $context->tag($name);

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
                $this->calls->render($tag, $proposal->callValues[$index] ?? [], $slotContents[$index] ?? ''),
            );
        }
        $files = count(array_unique(array_map(static fn (CloneOccurrence $o): string => $o->file, $occurrences)));

        return new ComponentCandidate(
            id: $id,
            name: $name,
            tag: $tag,
            sources: [$source],
            elementCount: $elementCount,
            occurrences: $candidateOccurrences,
            arguments: $arguments,
            slot: $this->slotProposal($alignment, $slotContents),
            reasons: $this->reasons($source, count($occurrences), $files, $arguments, $shared, $alignment),
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
                    sprintf(
                        '%s: suggested by fluid-lens from %s markup in %d files.',
                        $name,
                        $source === CandidateSource::Clone ? 'copied' : 'similar',
                        $files,
                    ),
                    'Review the arguments and the markup before use.',
                ],
                NamespaceDeclarations::fromSource($template->source) + $context->collectionNamespace(),
                $context->components->collectionDirectory(),
            ),
            existingMatch: $context->existingMatch($first->node),
        );
    }

    /**
     * @param non-empty-list<Node> $roots
     */
    private function alignment(CandidateSource $source, array $roots): ?StructureAlignment
    {
        if ($source !== CandidateSource::Clone) {
            return $this->structures->align($roots);
        }

        $points = $this->aligner->align($roots);

        return $points === null ? null : new StructureAlignment($points);
    }

    /**
     * An element only some copies have is rendered on a boolean argument that
     * defaults to what most copies do.
     */
    private function withOptionalElements(MarkupProposal $proposal, StructureAlignment $alignment): MarkupProposal
    {
        if ($alignment->presence === []) {
            return $proposal;
        }

        $namer = new ArgumentNamer();
        foreach ($proposal->arguments as $argument) {
            $namer->claim($argument->name);
        }

        $arguments = $proposal->arguments;
        $callValues = $proposal->callValues;
        foreach ($alignment->presence as $entry) {
            $present = count(array_filter($entry['present']));
            $default = $present * 2 >= count($entry['present']);
            $name = $namer->claim('show' . $this->elementName($entry['node']));

            $proposal->substitutions->wrapInCondition($entry['node'], '{' . $name . '}');
            $arguments[] = new ArgumentProposal(
                $name,
                ArgumentType::Bool,
                true,
                $default ? '{true}' : '{false}',
                ArgumentOrigin::Presence,
                [sprintf('present in %d of %d', $present, count($entry['present']))],
            );
            foreach ($entry['present'] as $copy => $isPresent) {
                if ($isPresent !== $default) {
                    $callValues[$copy][$name] = $isPresent ? '{true}' : '{false}';
                }
            }
        }

        return new MarkupProposal($arguments, $proposal->substitutions, $callValues, $proposal->caveats);
    }

    /**
     * Replaces the slot element's content with {@code <f:slot />} and returns,
     * per copy, the content its call passes in.
     *
     * @param non-empty-list<CloneOccurrence> $occurrences
     * @param array<string, ParsedTemplate>   $templates
     *
     * @return list<string>
     */
    private function withSlot(
        MarkupProposal $proposal,
        StructureAlignment $alignment,
        array $occurrences,
        array $templates,
    ): array {
        $slot = $alignment->slot;
        if ($slot === null) {
            return [];
        }

        $children = $slot->children();
        foreach ($children as $index => $child) {
            if ($index === 0) {
                $proposal->substitutions->replace($child, '<f:slot />');
            } else {
                $proposal->substitutions->drop($child);
            }
        }

        $contents = [];
        foreach ($occurrences as $copy => $occurrence) {
            $node = $alignment->slotNodes[$copy] ?? null;
            $source = $templates[$occurrence->file]->source ?? '';
            $contents[] = $node === null
                ? ''
                : $this->writer->write($node->children(), new Substitutions(), SourceCasing::fromSource($source));
        }

        return $contents;
    }

    /**
     * @param list<string> $contents
     */
    private function slotProposal(StructureAlignment $alignment, array $contents): ?SlotProposal
    {
        if ($alignment->slot === null) {
            return null;
        }

        $variants = [];
        foreach ($contents as $content) {
            $line = (string) preg_replace('/\s+/', ' ', trim($content));
            $variants[] = strlen($line) > 90 ? substr($line, 0, 87) . '...' : $line;
        }

        return new SlotProposal(
            sprintf('content of %s', $this->describe($alignment->slot)),
            array_values(array_unique($variants)),
        );
    }

    private function describe(Node $node): string
    {
        $classes = trim((string) $node->attribute('class'));

        return '<' . $node->name . '>' . ($classes === '' ? '' : '.' . str_replace(' ', '.', $classes));
    }

    /**
     * A short name for an element, used in its "show…" argument: the component
     * or ViewHelper name, its block class, or its tag.
     */
    private function elementName(Node $node): string
    {
        if (str_contains($node->name, ':')) {
            $parts = explode('.', explode(':', $node->name, 2)[1] ?? $node->name);

            return ComponentNamer::pascal((string) end($parts));
        }

        $classes = preg_split('/\s+/', trim((string) $node->attribute('class'))) ?: [];
        $class = (string) ($classes[0] ?? '');

        return ComponentNamer::pascal($class !== '' ? $class : $node->name);
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
    private function reasons(
        CandidateSource $source,
        int $copies,
        int $files,
        array $arguments,
        float $shared,
        StructureAlignment $alignment,
    ): array {
        $reasons = [sprintf(
            '%s markup appears %d× in %d file(s); %d%% of its values and texts are identical in every copy',
            $source === CandidateSource::Clone ? 'the same' : 'similar',
            $copies,
            $files,
            (int) round($shared * 100),
        )];
        if ($alignment->presence !== []) {
            $reasons[] = sprintf('%d element(s) only some copies have become optional', count($alignment->presence));
        }
        if ($alignment->slot !== null) {
            $reasons[] = sprintf('the content of %s differs and becomes the slot', $this->describe($alignment->slot));
        }

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
}
