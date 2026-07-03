<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Within one document, `id` values must be unique. Dynamic ids ({@code {expr}})
 * are ignored because their runtime value cannot be judged statically.
 *
 * WCAG 4.1.1 Parsing (Level A).
 */
final class DuplicateIdRule implements Rule
{
    public function name(): string
    {
        return 'wcag.duplicate-id';
    }

    public function check(ParsedTemplate $template): array
    {
        $elements = Elements::all($template->tree);
        $counts = $this->countIds($elements);

        $findings = [];
        foreach ($elements as $element) {
            $id = $element->attribute('id');
            if ($id !== null && ($counts[$id] ?? 0) > 1) {
                $findings[] = new Finding(
                    $this->name(),
                    Severity::Error,
                    sprintf('Duplicate id "%s".', $id),
                    $template->file,
                    $element->sourceRange?->startLine ?? 0,
                    'WCAG 4.1.1 (A)',
                );
            }
        }

        return $findings;
    }

    /**
     * @param list<\YellowTwins\FluidLens\Parser\Node> $elements
     *
     * @return array<string, int>
     */
    private function countIds(array $elements): array
    {
        $counts = [];
        foreach ($elements as $element) {
            $id = $element->attribute('id');
            if ($id !== null && trim($id) !== '' && !Attributes::isDynamic($id)) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
