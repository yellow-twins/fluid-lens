<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * An `aria-*` attribute name must be a real WAI-ARIA state or property; a typo
 * such as `aria-lable` is silently ignored by assistive technology.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class AriaAttributeRule extends AbstractElementRule
{
    /**
     * Valid WAI-ARIA 1.2 states and properties.
     *
     * @var list<string>
     */
    private const VALID = [
        'aria-activedescendant', 'aria-atomic', 'aria-autocomplete', 'aria-braillelabel',
        'aria-brailleroledescription', 'aria-busy', 'aria-checked', 'aria-colcount', 'aria-colindex',
        'aria-colindextext', 'aria-colspan', 'aria-controls', 'aria-current', 'aria-describedby',
        'aria-description', 'aria-details', 'aria-disabled', 'aria-dropeffect', 'aria-errormessage',
        'aria-expanded', 'aria-flowto', 'aria-grabbed', 'aria-haspopup', 'aria-hidden', 'aria-invalid',
        'aria-keyshortcuts', 'aria-label', 'aria-labelledby', 'aria-level', 'aria-live', 'aria-modal',
        'aria-multiline', 'aria-multiselectable', 'aria-orientation', 'aria-owns', 'aria-placeholder',
        'aria-posinset', 'aria-pressed', 'aria-readonly', 'aria-relevant', 'aria-required',
        'aria-roledescription', 'aria-rowcount', 'aria-rowindex', 'aria-rowindextext', 'aria-rowspan',
        'aria-selected', 'aria-setsize', 'aria-sort', 'aria-valuemax', 'aria-valuemin', 'aria-valuenow',
        'aria-valuetext',
    ];

    public function name(): string
    {
        return 'wcag.aria-attr';
    }

    protected function inspect(Node $element, string $file): array
    {
        $findings = [];
        foreach (array_keys($element->attributes) as $attribute) {
            if (str_starts_with($attribute, 'aria-') && !in_array($attribute, self::VALID, true)) {
                $findings[] = $this->finding(
                    $element,
                    Severity::Warning,
                    sprintf('Unknown ARIA attribute "%s".', $attribute),
                    $file,
                    'WCAG 4.1.2 (A)',
                );
            }
        }

        return $findings;
    }
}
