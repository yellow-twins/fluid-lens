<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * Headings should not skip levels (for example h2 straight to h4), because the
 * heading hierarchy conveys the document's structure. This is heuristic: it
 * compares consecutive headings in document order and does not resolve Fluid
 * conditionals, so it is reported as a warning.
 *
 * WCAG 1.3.1 Info and Relationships (Level A).
 */
final class HeadingOrderRule implements Rule
{
    public function name(): string
    {
        return 'wcag.heading-order';
    }

    public function check(ParsedTemplate $template): array
    {
        $findings = [];
        $previousLevel = 0;

        foreach (Elements::all($template->tree) as $element) {
            $level = $this->headingLevel($element);
            if ($level === null) {
                continue;
            }

            if ($previousLevel > 0 && $level > $previousLevel + 1) {
                $findings[] = new Finding(
                    $this->name(),
                    Severity::Warning,
                    sprintf('Heading level jumps from h%d to h%d; do not skip levels.', $previousLevel, $level),
                    $template->file,
                    $element->sourceRange?->startLine ?? 0,
                    'WCAG 1.3.1 (A)',
                );
            }

            $previousLevel = $level;
        }

        return $findings;
    }

    private function headingLevel(Node $element): ?int
    {
        if (preg_match('/^h([1-6])$/', $element->name, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
