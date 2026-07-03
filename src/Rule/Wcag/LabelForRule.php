<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * A `<label for="x">` should point at a control with `id="x"`. This is reported
 * conservatively, as a notice, because a target could in principle live in
 * another partial: templates using Fluid form ViewHelpers (which generate ids
 * automatically) are skipped entirely, and only static `for`/`id` values are
 * considered.
 *
 * WCAG 1.3.1 Info and Relationships / 4.1.2 Name, Role, Value (Level A).
 */
final class LabelForRule implements Rule
{
    public function name(): string
    {
        return 'wcag.label-for';
    }

    public function check(ParsedTemplate $template): array
    {
        $elements = Elements::all($template->tree);
        if ($this->usesFluidForm($elements)) {
            return [];
        }

        $ids = $this->staticIds($elements);

        $findings = [];
        foreach ($elements as $element) {
            $for = $element->name === 'label' ? $element->attribute('for') : null;
            if ($for === null || $for === '' || Attributes::isDynamic($for) || isset($ids[$for])) {
                continue;
            }

            $findings[] = new Finding(
                $this->name(),
                Severity::Notice,
                sprintf('<label for="%s"> has no matching id in this template.', $for),
                $template->file,
                $element->sourceRange?->startLine ?? 0,
                'WCAG 1.3.1 (A)',
            );
        }

        return $findings;
    }

    /**
     * @param list<Node> $elements
     */
    private function usesFluidForm(array $elements): bool
    {
        foreach ($elements as $element) {
            if (str_starts_with($element->name, 'f:form')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Node> $elements
     *
     * @return array<string, true>
     */
    private function staticIds(array $elements): array
    {
        $ids = [];
        foreach ($elements as $element) {
            $id = $element->attribute('id');
            if ($id !== null && $id !== '' && !Attributes::isDynamic($id)) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }
}
