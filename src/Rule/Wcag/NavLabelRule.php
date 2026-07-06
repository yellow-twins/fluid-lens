<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Support\Roles;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * When a page has more than one navigation landmark, each needs a distinguishing
 * `aria-label`/`aria-labelledby` so users can tell them apart. Reported only when
 * two or more navigation landmarks share a template, and advisory (Notice)
 * because further landmarks may live in other partials.
 *
 * WCAG 1.3.1 Info and Relationships (Level A), technique H.
 */
final class NavLabelRule implements Rule
{
    public function name(): string
    {
        return 'wcag.nav-label';
    }

    public function check(ParsedTemplate $template): array
    {
        $navs = array_values(array_filter(
            Elements::all($template->tree),
            fn (Node $element): bool => $this->isNavigationLandmark($element),
        ));

        if (count($navs) < 2) {
            return [];
        }

        $findings = [];
        foreach ($navs as $nav) {
            if (Attributes::present($nav, 'aria-label') || Attributes::present($nav, 'aria-labelledby')) {
                continue;
            }

            $findings[] = new Finding(
                $this->name(),
                Severity::Notice,
                'Multiple navigation landmarks; this one has no aria-label to tell them apart.',
                $template->file,
                $nav->sourceRange?->startLine ?? 0,
                'WCAG 1.3.1 (A)',
            );
        }

        return $findings;
    }

    private function isNavigationLandmark(Node $element): bool
    {
        return $element->name === 'nav' || Roles::has($element, 'navigation');
    }
}
