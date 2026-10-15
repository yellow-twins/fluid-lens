<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Analysis;

use YellowTwins\FluidLens\Component\Model\CandidateNote;
use YellowTwins\FluidLens\Component\Model\ExclusionReason;
use YellowTwins\FluidLens\Fluid\VariableReferences;
use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Support\Elements;

/**
 * Decides whether a block of markup can live in a Fluid component.
 *
 * A component renders in isolation: it sees only its arguments and has its own
 * ViewHelper variable container. Anything that relies on the caller's context
 * therefore either rules the conversion out (a blocker) or has to be passed in
 * explicitly (a caveat).
 */
final class ComponentSuitability
{
    public function __construct(
        private readonly VariableReferences $variables = new VariableReferences(),
    ) {
    }

    /**
     * @param Node   $candidate the markup that would move into the component
     * @param Node   $scope     the tree whose sections the markup may render (its file)
     * @param string $file      the file the markup lives in
     */
    public function assess(Node $candidate, Node $scope, string $file): Assessment
    {
        $blockers = [];
        $caveats = [];

        if ($this->usesFieldsOutsideForm($candidate)) {
            $blockers[] = ExclusionReason::FormContext;
        }

        if ($this->rendersForeignSection($candidate, $scope)) {
            $blockers[] = ExclusionReason::CallerSection;
        }

        if ($this->belongsToLayout($candidate, $file)) {
            $blockers[] = ExclusionReason::Layout;
        }

        if ($candidate->name === 'f:section') {
            $blockers[] = ExclusionReason::TemplateSection;
        }

        $variables = $this->variables->in($candidate);
        if (isset($variables['settings'])) {
            $caveats[] = new CandidateNote(
                CandidateNote::IMPLICIT_SETTINGS,
                sprintf(
                    'reads %s; Partials see the settings implicitly, a component only gets what is passed',
                    implode(', ', array_map(
                        static fn (string $path): string => '{' . $path . '}',
                        $variables['settings']->paths,
                    )),
                ),
            );
        }

        if (isset($variables['_all'])) {
            $caveats[] = new CandidateNote(
                CandidateNote::FORWARDS_ALL,
                'forwards {_all}; inside a component that is only the component\'s own arguments',
            );
        }

        return new Assessment($blockers, $caveats);
    }

    private function usesFieldsOutsideForm(Node $candidate): bool
    {
        foreach (Elements::all($candidate) as $element) {
            if (str_starts_with($element->name, 'f:form.') && !$this->hasFormAncestor($element, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function hasFormAncestor(Node $element, Node $boundary): bool
    {
        for ($node = $element->parent(); $node !== null; $node = $node->parent()) {
            if ($node->name === 'f:form') {
                return true;
            }
            if ($node === $boundary) {
                return false;
            }
        }

        return false;
    }

    private function rendersForeignSection(Node $candidate, Node $scope): bool
    {
        $defined = [];
        foreach (Elements::all($scope) as $element) {
            if ($element->name === 'f:section') {
                $defined[(string) $element->attribute('name')] = true;
            }
        }

        foreach (Elements::all($candidate) as $element) {
            $section = $element->attribute('section');
            $renders = $element->name === 'f:render' && $element->attribute('partial') === null;
            if ($renders && $section !== null && !isset($defined[$section])) {
                return true;
            }
        }

        return false;
    }

    private function belongsToLayout(Node $candidate, string $file): bool
    {
        if (str_contains(str_replace('\\', '/', $file), '/Layouts/')) {
            return true;
        }

        foreach (Elements::all($candidate) as $element) {
            if ($element->name === 'f:layout') {
                return true;
            }
        }

        return false;
    }
}
