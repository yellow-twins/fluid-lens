<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;
use YellowTwins\FluidLens\Support\DomIds;
use YellowTwins\FluidLens\Support\Elements;
use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * `aria-controls` must reference the id of the element it operates. When no
 * matching id exists in the template the relationship is broken. Advisory
 * (Notice), because the target legitimately lives in another partial — the same
 * conservative stance as {@see LabelForRule}. Dynamic values are ignored.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class AriaControlsTargetRule implements Rule
{
    public function name(): string
    {
        return 'wcag.aria-controls-target';
    }

    public function check(ParsedTemplate $template): array
    {
        $ids = DomIds::declaredIn($template->tree);

        $findings = [];
        foreach (Elements::all($template->tree) as $element) {
            $value = $element->attribute('aria-controls');
            if ($value === null || trim($value) === '' || Attributes::isDynamic($value)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($value)) ?: [] as $target) {
                if (!isset($ids[$target])) {
                    $findings[] = new Finding(
                        $this->name(),
                        Severity::Notice,
                        sprintf('aria-controls="%s" has no matching id in this template.', $target),
                        $template->file,
                        $element->sourceRange?->startLine ?? 0,
                        'WCAG 4.1.2 (A)',
                    );
                }
            }
        }

        return $findings;
    }
}
