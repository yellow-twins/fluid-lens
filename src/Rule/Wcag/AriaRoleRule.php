<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule\Wcag;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Rule\AbstractElementRule;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Attributes;

/**
 * A `role` attribute must name a valid WAI-ARIA role; a misspelt or invented role
 * is ignored by assistive technology. Dynamic values are not judged.
 *
 * WCAG 4.1.2 Name, Role, Value (Level A).
 */
final class AriaRoleRule extends AbstractElementRule
{
    /**
     * Valid WAI-ARIA 1.2 roles.
     *
     * @var list<string>
     */
    private const VALID_ROLES = [
        'alert', 'alertdialog', 'application', 'article', 'banner', 'blockquote', 'button', 'caption',
        'cell', 'checkbox', 'code', 'columnheader', 'combobox', 'complementary', 'contentinfo', 'definition',
        'deletion', 'dialog', 'directory', 'document', 'emphasis', 'feed', 'figure', 'form', 'generic', 'grid',
        'gridcell', 'group', 'heading', 'img', 'insertion', 'link', 'list', 'listbox', 'listitem', 'log', 'main',
        'marquee', 'math', 'menu', 'menubar', 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'meter',
        'navigation', 'none', 'note', 'option', 'paragraph', 'presentation', 'progressbar', 'radio', 'radiogroup',
        'region', 'row', 'rowgroup', 'rowheader', 'scrollbar', 'search', 'searchbox', 'separator', 'slider',
        'spinbutton', 'status', 'strong', 'subscript', 'superscript', 'switch', 'tab', 'table', 'tablist',
        'tabpanel', 'term', 'textbox', 'time', 'timer', 'toolbar', 'tooltip', 'tree', 'treegrid', 'treeitem',
    ];

    public function name(): string
    {
        return 'wcag.aria-role';
    }

    protected function inspect(Node $element, string $file): array
    {
        $role = $element->attribute('role');
        if ($role === null || Attributes::isDynamic($role)) {
            return [];
        }

        $findings = [];
        foreach (preg_split('/\s+/', trim($role)) ?: [] as $token) {
            if ($token !== '' && !in_array($token, self::VALID_ROLES, true)) {
                $findings[] = $this->finding(
                    $element,
                    Severity::Warning,
                    sprintf('Unknown ARIA role "%s".', $token),
                    $file,
                    'WCAG 4.1.2 (A)',
                );
            }
        }

        return $findings;
    }
}
