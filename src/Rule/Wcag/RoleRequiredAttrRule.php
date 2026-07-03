<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Roles;

/**
 * Some ARIA roles carry a required state without which the widget is incomplete:
 * a `checkbox`/`switch`/`radio` needs `aria-checked`, a `slider` needs
 * `aria-valuenow`.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class RoleRequiredAttrRule extends AbstractElementRule
{
    /**
     * @var array<string, string>
     */
    private const REQUIRED = [
        'checkbox' => 'aria-checked',
        'switch' => 'aria-checked',
        'radio' => 'aria-checked',
        'menuitemcheckbox' => 'aria-checked',
        'menuitemradio' => 'aria-checked',
        'slider' => 'aria-valuenow',
    ];

    public function name(): string
    {
        return 'wcag.role-required-attr';
    }

    protected function inspect(Node $element, string $file): array
    {
        $findings = [];
        foreach (self::REQUIRED as $role => $attribute) {
            if (Roles::has($element, $role) && $element->attribute($attribute) === null) {
                $findings[] = $this->finding(
                    $element,
                    Severity::Warning,
                    sprintf('role="%s" requires %s.', $role, $attribute),
                    $file,
                    'WCAG 4.1.2 (A)',
                );
            }
        }

        return $findings;
    }
}
