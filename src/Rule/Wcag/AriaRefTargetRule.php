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
 * `aria-labelledby` and `aria-describedby` name (or describe) an element by
 * referencing other ids. A reference with no matching id contributes no
 * accessible name. Advisory (Notice), because the target may live in another
 * partial — the same conservative stance as {@see LabelForRule}. Dynamic values
 * are ignored.
 *
 * WCAG 1.3.1 / 4.1.2 (Level A).
 */
final class AriaRefTargetRule implements Rule
{
    /**
     * @var list<string>
     */
    private const ATTRIBUTES = ['aria-labelledby', 'aria-describedby'];

    public function name(): string
    {
        return 'wcag.aria-ref-target';
    }

    public function check(ParsedTemplate $template): array
    {
        $ids = DomIds::declaredIn($template->tree);

        $findings = [];
        foreach (Elements::all($template->tree) as $element) {
            foreach (self::ATTRIBUTES as $attribute) {
                foreach ($this->missingTargets($element, $attribute, $ids) as $target) {
                    $findings[] = new Finding(
                        $this->name(),
                        Severity::Notice,
                        sprintf('%s="%s" has no matching id in this template.', $attribute, $target),
                        $template->file,
                        $element->sourceRange?->startLine ?? 0,
                        'WCAG 1.3.1 (A)',
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @param array<string, true> $ids
     *
     * @return list<string>
     */
    private function missingTargets(Node $element, string $attribute, array $ids): array
    {
        $value = $element->attribute($attribute);
        if ($value === null || trim($value) === '' || Attributes::isDynamic($value)) {
            return [];
        }

        $missing = [];
        foreach (preg_split('/\s+/', trim($value)) ?: [] as $target) {
            if (!isset($ids[$target])) {
                $missing[] = $target;
            }
        }

        return $missing;
    }
}
