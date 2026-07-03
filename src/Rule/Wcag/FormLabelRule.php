<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * Form controls need a programmatic label. This flags a control that has no way
 * to be labelled at all — no id for a `<label for>` to reference, and no
 * aria-label/aria-labelledby/title. Controls that carry their own name (submit,
 * button, image, reset, hidden inputs) are exempt.
 *
 * WCAG 4.1.2 Name, Role, Value / 3.3.2 Labels or Instructions (Level A).
 */
final class FormLabelRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const CONTROLS = ['input', 'select', 'textarea'];

    /**
     * @var list<string>
     */
    private const SELF_LABELLING_INPUT_TYPES = ['hidden', 'submit', 'button', 'image', 'reset'];

    /**
     * @var list<string>
     */
    private const LABELLING_ATTRIBUTES = ['id', 'aria-label', 'aria-labelledby', 'title'];

    public function name(): string
    {
        return 'wcag.form-label';
    }

    protected function inspect(Node $element, string $file): array
    {
        if (!in_array($element->name, self::CONTROLS, true) || $this->isExemptInput($element)) {
            return [];
        }

        foreach (self::LABELLING_ATTRIBUTES as $attribute) {
            if (Attributes::present($element, $attribute)) {
                return [];
            }
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('<%s> may have no label. Add a <label for>, or an aria-label.', $element->name),
                $file,
                'WCAG 4.1.2 (A)',
            ),
        ];
    }

    private function isExemptInput(Node $element): bool
    {
        return $element->name === 'input'
            && in_array($element->attribute('type'), self::SELF_LABELLING_INPUT_TYPES, true);
    }
}
