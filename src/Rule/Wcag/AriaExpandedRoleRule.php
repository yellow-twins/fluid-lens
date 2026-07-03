<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * `aria-expanded` marks the trigger of a disclosure or accordion, so it only
 * belongs on something the user can operate. A `<div aria-expanded>` header —
 * with no button and no interactive role — is a common broken accordion that a
 * keyboard or screen-reader user cannot toggle.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class AriaExpandedRoleRule extends AbstractElementRule
{
    /**
     * @var list<string>
     */
    private const NATIVE_INTERACTIVE = ['button', 'input', 'select', 'textarea', 'summary'];

    /**
     * @var list<string>
     */
    private const INTERACTIVE_ROLES = [
        'button', 'tab', 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'combobox', 'checkbox',
        'switch', 'treeitem', 'link', 'option', 'gridcell', 'columnheader', 'rowheader',
    ];

    public function name(): string
    {
        return 'wcag.aria-expanded-role';
    }

    protected function inspect(Node $element, string $file): array
    {
        if ($element->attribute('aria-expanded') === null || $this->isInteractive($element)) {
            return [];
        }

        return [
            $this->finding(
                $element,
                Severity::Warning,
                sprintf('<%s> has aria-expanded but is not interactive; use a button or a role.', $element->name),
                $file,
                'WCAG 4.1.2 (A)',
            ),
        ];
    }

    private function isInteractive(Node $element): bool
    {
        if ($element->name === 'a') {
            return Attributes::present($element, 'href');
        }

        if (in_array($element->name, self::NATIVE_INTERACTIVE, true)) {
            return true;
        }

        return $this->hasInteractiveRole($element);
    }

    private function hasInteractiveRole(Node $element): bool
    {
        foreach (preg_split('/\s+/', trim($element->attribute('role') ?? '')) ?: [] as $role) {
            if (in_array($role, self::INTERACTIVE_ROLES, true)) {
                return true;
            }
        }

        return false;
    }
}
