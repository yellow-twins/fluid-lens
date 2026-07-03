<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * A boolean-state ARIA attribute must be "true" or "false" (or "mixed" for
 * checked/pressed) — a value like "yes" or "1" is ignored.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class AriaBooleanRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const BOOLEAN = [
        'aria-hidden', 'aria-expanded', 'aria-atomic', 'aria-busy', 'aria-disabled', 'aria-modal',
        'aria-multiline', 'aria-multiselectable', 'aria-readonly', 'aria-required', 'aria-selected',
    ];

    /**
     * @var list<string>
     */
    private const TRISTATE = ['aria-checked', 'aria-pressed'];

    public function name(): string
    {
        return 'wcag.aria-boolean';
    }

    protected function inspect(Node $element, string $file): array
    {
        $findings = [];
        foreach ($element->attributes as $name => $value) {
            $allowed = $this->allowedValues($name);
            if ($allowed === [] || Attributes::isDynamic($value) || in_array(strtolower($value), $allowed, true)) {
                continue;
            }

            $findings[] = $this->finding(
                $element,
                Severity::Warning,
                sprintf('%s="%s" must be %s.', $name, $value, implode('/', $allowed)),
                $file,
                'WCAG 4.1.2 (A)',
            );
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function allowedValues(string $name): array
    {
        if (in_array($name, self::BOOLEAN, true)) {
            return ['true', 'false', 'undefined'];
        }

        if (in_array($name, self::TRISTATE, true)) {
            return ['true', 'false', 'mixed', 'undefined'];
        }

        return [];
    }
}
