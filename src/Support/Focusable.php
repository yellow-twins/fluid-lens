<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Decides whether an element can receive keyboard focus.
 *
 * An explicit numeric `tabindex` wins: a non-negative value makes any element
 * focusable, a negative one removes it. Otherwise the natively focusable elements
 * (links with an href, form controls, iframes) count. Dynamic tabindex values are
 * treated as unknown and fall back to the native rule, to avoid false positives.
 */
final class Focusable
{
    /**
     * @var list<string>
     */
    private const NATIVELY_FOCUSABLE = ['button', 'select', 'textarea', 'iframe'];

    public static function isFocusable(Node $node): bool
    {
        $tabindex = $node->attribute('tabindex');
        if ($tabindex !== null && !Attributes::isDynamic($tabindex) && is_numeric($tabindex)) {
            return (int) $tabindex >= 0;
        }

        return self::isNativelyFocusable($node);
    }

    private static function isNativelyFocusable(Node $node): bool
    {
        if ($node->name === 'a') {
            return Attributes::present($node, 'href');
        }

        if ($node->name === 'input') {
            return $node->attribute('type') !== 'hidden';
        }

        return in_array($node->name, self::NATIVELY_FOCUSABLE, true);
    }
}
