<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Analysis\ComponentSuitability;
use YellowTwins\FluidLens\Component\Draft\DraftRenderer;
use YellowTwins\FluidLens\Component\Draft\ExampleCallRenderer;
use YellowTwins\FluidLens\Component\Draft\FluidMarkupWriter;
use YellowTwins\FluidLens\Component\Draft\Substitutions;
use YellowTwins\FluidLens\Component\Index\PartialFile;
use YellowTwins\FluidLens\Component\Model\ArgumentOrigin;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\ArgumentType;
use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Model\CandidateOccurrence;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\KeptPartial;
use YellowTwins\FluidLens\Component\Model\PartialUsage;
use YellowTwins\FluidLens\Component\Proposal\ArgumentNamer;
use YellowTwins\FluidLens\Component\Proposal\ArgumentTypeInferrer;
use YellowTwins\FluidLens\Component\Proposal\ComponentNamer;
use YellowTwins\FluidLens\Component\Proposal\SettingsArguments;
use YellowTwins\FluidLens\Fluid\NamespaceDeclarations;
use YellowTwins\FluidLens\Fluid\RenderCall;
use YellowTwins\FluidLens\Fluid\SourceCasing;
use YellowTwins\FluidLens\Fluid\VariableReferences;
use YellowTwins\FluidLens\Fluid\VariableUsage;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;
use YellowTwins\FluidLens\Support\Elements;

/**
 * Turns a Partial that is rendered from several places into a component
 * suggestion — or records why it has to stay a Partial.
 *
 * The arguments come from what the Partial reads: arguments it already declares
 * with f:argument are taken over unchanged, every other free variable becomes an
 * argument, and implicitly available TypoScript settings become explicit ones.
 */
final class PartialCandidateFactory
{
    /**
     * Elements that are Partial bookkeeping, not markup of the component body.
     */
    private const BOOKKEEPING = ['f:argument', 'f:comment', 'f:layout'];

    private const EXTENSION_OF = '#/([^/]+)/Resources/Private/#';

    private const DECLARED_BONUS = 1.3;
    private const EXTENSIONS_BONUS = 1.2;
    private const SETTINGS_PENALTY = 0.8;

    public function __construct(
        private readonly CandidateScorer $scorer = new CandidateScorer(),
        private readonly ComponentSuitability $suitability = new ComponentSuitability(),
        private readonly VariableReferences $variables = new VariableReferences(),
        private readonly ArgumentTypeInferrer $types = new ArgumentTypeInferrer(),
        private readonly ComponentNamer $namer = new ComponentNamer(),
        private readonly FluidMarkupWriter $writer = new FluidMarkupWriter(),
        private readonly DraftRenderer $drafts = new DraftRenderer(),
        private readonly ExampleCallRenderer $calls = new ExampleCallRenderer(),
        private readonly SettingsArguments $settings = new SettingsArguments(),
    ) {
    }

    /**
     * @param non-empty-list<RenderCall> $calls the render calls resolved to this Partial
     *
     * @return ComponentCandidate|KeptPartial|null null when the Partial only wraps an existing component
     */
    public function create(
        PartialFile $partial,
        array $calls,
        DraftContext $context,
    ): ComponentCandidate|KeptPartial|null {
        $template = $partial->template;
        $container = $this->container($template->tree);
        if ($this->onlyCallsAComponent($container, $context)) {
            return null;
        }

        $assessment = $this->suitability->assess($container, $template->tree, $template->file);
        if ($assessment->isBlocked()) {
            return new KeptPartial($partial->name, $template->file, count($calls), $assessment->blockers);
        }

        $substitutions = new Substitutions();
        $declared = $this->declaredArguments($container, $substitutions);
        [$arguments, $inputs] = $this->proposeArguments($container, $declared, $calls, $substitutions);

        $caveats = [...$assessment->caveats, ...$this->callerCaveats($calls)];
        if ($this->scorer->isTooConfigurable(count($arguments))) {
            $caveats[] = new CandidateNote(
                CandidateNote::TOO_CONFIGURABLE,
                sprintf('%d arguments — a component this configurable is often two components', count($arguments)),
            );
        }
        $collectionCaveat = $context->collectionCaveat();
        if ($collectionCaveat !== null) {
            $caveats[] = $collectionCaveat;
        }

        $name = $context->names->claimFirst($this->namer->partialNameOptions($partial->name));
        $body = self::bodyElements($template->tree);
        $tag = $context->tag($name);
        $elements = $this->elementCount($container);
        $files = $this->callerFiles($calls);

        return new ComponentCandidate(
            id: 'partial:' . $template->file,
            name: $name,
            tag: $tag,
            sources: [CandidateSource::Partial],
            elementCount: $elements,
            occurrences: array_map(
                fn (RenderCall $call): CandidateOccurrence => new CandidateOccurrence(
                    $call->file,
                    $call->line,
                    $this->calls->render($tag, $this->callArguments($call, $arguments, $inputs)),
                ),
                $calls,
            ),
            arguments: $arguments,
            slot: null,
            reasons: $this->reasons($partial, $calls, $declared),
            caveats: $caveats,
            score: $this->scorer->score(
                count($calls),
                $elements,
                count($files),
                count($arguments),
                $this->adjustments($calls, $declared, $caveats),
            ),
            partial: new PartialUsage([$partial->name], [$template->file]),
            draft: $this->drafts->render(
                $name,
                $arguments,
                $this->writer->write(
                    $this->body($container),
                    $substitutions,
                    SourceCasing::fromSource($template->source),
                ),
                [
                    sprintf('%s: suggested by fluid-lens from the Partial "%s".', $name, $partial->name),
                    'Review the arguments and the markup before use.',
                ],
                NamespaceDeclarations::fromSource($template->source) + $context->collectionNamespace(),
                $context->components->collectionDirectory(),
            ),
            existingMatch: count($body) === 1 ? $context->existingMatch($body[0]) : null,
        );
    }

    /**
     * The node holding the Partial's content: the Fluid namespace wrapper
     * ({@code <html data-namespace-typo3-fluid>}) when present, otherwise the root.
     */
    private function container(Node $root): Node
    {
        foreach ($root->elementChildren() as $child) {
            if ($child->name === 'html') {
                return $child;
            }
        }

        return $root;
    }

    /**
     * The elements a Partial (or component) file consists of, without the Fluid
     * namespace wrapper and bookkeeping such as f:argument.
     *
     * @return list<Node>
     */
    public static function bodyElements(Node $tree): array
    {
        $container = $tree;
        foreach ($tree->elementChildren() as $child) {
            if ($child->name === 'html') {
                $container = $child;
                break;
            }
        }

        return array_values(array_filter(
            $container->elementChildren(),
            static fn (Node $node): bool => !in_array($node->name, self::BOOKKEEPING, true),
        ));
    }

    /**
     * A Partial that only wraps a call of an existing component already is as
     * reusable as it gets.
     */
    private function onlyCallsAComponent(Node $container, DraftContext $context): bool
    {
        $elements = [];
        foreach ($this->body($container) as $node) {
            if ($node->isElement()) {
                $elements[] = $node;
            } elseif ($node->type === NodeType::Text && trim($node->text) !== '') {
                return false;
            }
        }

        return count($elements) === 1 && $context->components->isComponentTag($elements[0]->name);
    }

    /**
     * @return list<Node>
     */
    private function body(Node $container): array
    {
        return array_values(array_filter(
            $container->children(),
            fn (Node $node): bool => !in_array($node->name, self::BOOKKEEPING, true)
                && !($node->type === NodeType::Text && preg_match('/^\s*\{namespace\s/', $node->text) === 1),
        ));
    }

    /**
     * @return array<string, ArgumentProposal>
     */
    private function declaredArguments(Node $container, Substitutions $substitutions): array
    {
        $declared = [];
        foreach ($container->elementChildren() as $element) {
            if ($element->name !== 'f:argument') {
                continue;
            }

            $name = (string) $element->attribute('name');
            $type = (string) ($element->attribute('type') ?? 'mixed');
            $declared[$name] = new ArgumentProposal(
                $name,
                $this->typeOf($type),
                in_array(strtolower((string) $element->attribute('optional')), ['{true}', 'true', '1'], true),
                $element->attribute('default'),
                ArgumentOrigin::Declared,
                [],
                (string) $element->attribute('description'),
                $type,
            );
            $substitutions->drop($element);
        }

        return $declared;
    }

    /**
     * @param array<string, ArgumentProposal> $declared
     * @param non-empty-list<RenderCall>      $calls
     *
     * @return array{0: list<ArgumentProposal>, 1: array<string, string>} the arguments, and per
     *                                                                      argument what a caller passes:
     *                                                                      a variable name or a settings
     *                                                                      expression in braces
     */
    private function proposeArguments(
        Node $container,
        array $declared,
        array $calls,
        Substitutions $substitutions,
    ): array {
        $namer = new ArgumentNamer();
        foreach (array_keys($declared) as $name) {
            $namer->claim($name);
        }

        $arguments = array_values($declared);
        $inputs = array_combine(array_keys($declared), array_keys($declared));

        foreach ($this->variables->in($container) as $root => $usage) {
            if (isset($declared[$root]) || $root === '_all') {
                continue;
            }

            if ($root === 'settings') {
                foreach ($this->settings->propose($usage, $namer, $substitutions) as $name => $argument) {
                    $inputs[$name] = $argument->observedValues[0] ?? '{settings}';
                    $arguments[] = $argument;
                }
                continue;
            }

            $name = $namer->claim($root);
            if ($name !== $root) {
                $substitutions->renameVariable($root, $name);
            }
            $inputs[$name] = $root;
            $arguments[] = $this->variableArgument($name, $root, $usage, $calls);
        }

        $proposed = $this->requiredFirst(array_slice($arguments, count($declared)));

        return [[...array_values($declared), ...$proposed], $inputs];
    }

    /**
     * Orders proposed arguments so the required ones come first, keeping the
     * order within each group.
     *
     * @param list<ArgumentProposal> $arguments
     *
     * @return list<ArgumentProposal>
     */
    private function requiredFirst(array $arguments): array
    {
        return [
            ...array_values(array_filter($arguments, static fn (ArgumentProposal $a): bool => !$a->optional)),
            ...array_values(array_filter($arguments, static fn (ArgumentProposal $a): bool => $a->optional)),
        ];
    }

    /**
     * @param non-empty-list<RenderCall> $calls
     */
    private function variableArgument(string $name, string $root, VariableUsage $usage, array $calls): ArgumentProposal
    {
        $values = [];
        foreach ($calls as $call) {
            if (isset($call->arguments[$root])) {
                $values[] = $call->arguments[$root];
            }
        }
        $values = array_values(array_unique($values));

        $type = $this->types->infer($values, $usage);
        $optional = $this->isOptional($root, $calls);

        return new ArgumentProposal(
            $name,
            $type,
            $optional,
            $optional ? $this->defaultFor($type) : null,
            ArgumentOrigin::Variable,
            array_slice($values, 0, 4),
        );
    }

    /**
     * Optional when at least one caller whose arguments are known does not pass
     * it. Callers passing {_all} could pass anything, so they decide nothing.
     *
     * @param non-empty-list<RenderCall> $calls
     */
    private function isOptional(string $root, array $calls): bool
    {
        foreach ($calls as $call) {
            if ($call->argumentsAreKnown() && !isset($call->arguments[$root])) {
                return true;
            }
        }

        return false;
    }

    private function defaultFor(ArgumentType $type): ?string
    {
        return match ($type) {
            ArgumentType::String => '',
            ArgumentType::Bool => '{false}',
            default => null,
        };
    }

    /**
     * @param list<ArgumentProposal> $arguments
     * @param array<string, string>  $inputs    argument name => caller variable or {settings…} expression
     *
     * @return array<string, string>
     */
    private function callArguments(RenderCall $call, array $arguments, array $inputs): array
    {
        $values = [];
        foreach ($arguments as $argument) {
            $source = $inputs[$argument->name] ?? $argument->name;
            $value = match (true) {
                str_starts_with($source, '{') => $source,
                isset($call->arguments[$source]) => $call->arguments[$source],
                $call->passesAll && !$argument->optional => '{' . $source . '}',
                default => null,
            };

            if ($value !== null) {
                $values[$argument->name] = $value;
            }
        }

        return $values;
    }

    /**
     * @param non-empty-list<RenderCall> $calls
     *
     * @return list<CandidateNote>
     */
    private function callerCaveats(array $calls): array
    {
        $caveats = [];

        $passingAll = count(array_filter($calls, static fn (RenderCall $call): bool => $call->passesAll));
        if ($passingAll > 0) {
            $caveats[] = new CandidateNote(
                CandidateNote::CALLERS_PASS_ALL,
                sprintf(
                    '%d of %d caller(s) pass arguments="{_all}"; the arguments are read from the Partial body,'
                        . ' check them at those call sites',
                    $passingAll,
                    count($calls),
                ),
            );
        }

        $signatures = [];
        foreach ($calls as $call) {
            if ($call->argumentsAreKnown()) {
                $names = $call->argumentNames();
                sort($names);
                $signatures[implode(',', $names)] = true;
            }
        }
        if (count($signatures) > 1) {
            $caveats[] = new CandidateNote(
                CandidateNote::CALLERS_DISAGREE,
                'callers pass different arguments; those not passed everywhere are proposed as optional',
            );
        }

        if (array_filter($calls, static fn (RenderCall $call): bool => $call->section !== null) !== []) {
            $caveats[] = new CandidateNote(
                CandidateNote::PARTIAL_SECTIONS,
                'some callers render a single section of this Partial; a component has no sections',
            );
        }

        return $caveats;
    }

    /**
     * @param non-empty-list<RenderCall>      $calls
     * @param array<string, ArgumentProposal> $declared
     *
     * @return list<string>
     */
    private function reasons(PartialFile $partial, array $calls, array $declared): array
    {
        $files = $this->callerFiles($calls);
        $reasons = [sprintf(
            'the Partial "%s" is rendered %d× from %d file(s)',
            $partial->name,
            count($calls),
            count($files),
        )];

        $extensions = $this->extensions($calls);
        if (count($extensions) > 1) {
            $reasons[] = sprintf('callers span %d extensions (%s)', count($extensions), implode(', ', $extensions));
        }

        if ($declared !== []) {
            $reasons[] = 'it already declares its arguments with f:argument — a component contract in all but name';
        }

        return $reasons;
    }

    /**
     * @param non-empty-list<RenderCall>      $calls
     * @param array<string, ArgumentProposal> $declared
     * @param list<CandidateNote>             $caveats
     *
     * @return array<string, float>
     */
    private function adjustments(array $calls, array $declared, array $caveats): array
    {
        $adjustments = [];
        if ($declared !== []) {
            $adjustments['declares arguments'] = self::DECLARED_BONUS;
        }
        if (count($this->extensions($calls)) > 1) {
            $adjustments['several extensions'] = self::EXTENSIONS_BONUS;
        }
        foreach ($caveats as $caveat) {
            if ($caveat->code === CandidateNote::IMPLICIT_SETTINGS) {
                $adjustments['implicit settings'] = self::SETTINGS_PENALTY;
            }
        }

        return $adjustments;
    }

    /**
     * @param non-empty-list<RenderCall> $calls
     *
     * @return list<string>
     */
    private function callerFiles(array $calls): array
    {
        return array_values(array_unique(array_map(static fn (RenderCall $call): string => $call->file, $calls)));
    }

    /**
     * @param non-empty-list<RenderCall> $calls
     *
     * @return list<string>
     */
    private function extensions(array $calls): array
    {
        $extensions = [];
        foreach ($calls as $call) {
            if (preg_match(self::EXTENSION_OF, str_replace('\\', '/', $call->file), $match) === 1) {
                $extensions[$match[1]] = true;
            }
        }

        $names = array_keys($extensions);
        sort($names);

        return $names;
    }

    private function elementCount(Node $container): int
    {
        $count = 0;
        foreach (Elements::all($container) as $element) {
            if ($element !== $container && !in_array($element->name, self::BOOKKEEPING, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function typeOf(string $declared): ArgumentType
    {
        return match (strtolower($declared)) {
            'string' => ArgumentType::String,
            'int', 'integer' => ArgumentType::Int,
            'float', 'double' => ArgumentType::Float,
            'bool', 'boolean' => ArgumentType::Bool,
            'array', 'iterable' => ArgumentType::Array,
            'mixed' => ArgumentType::Mixed,
            default => ArgumentType::Object,
        };
    }
}
