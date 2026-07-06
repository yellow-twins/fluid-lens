<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;
use YellowTwins\FluidLens\Parser\NodeType;

/**
 * Helpers for spotting how a template uses Fluid, which comes in two forms: the
 * *tag* form (`<f:render>`, kept as an element) and the *inline* form
 * (`{f:render(...)}`, kept as opaque text — in a text node or an attribute
 * value). These let the Fluid-specific consistency checks tell the two apart.
 */
final class FluidSyntax
{
    public static function hasElement(Node $tree, string $name): bool
    {
        foreach (Elements::all($tree) as $element) {
            if ($element->name === $name) {
                return true;
            }
        }

        return false;
    }

    public static function hasElementWithPrefix(Node $tree, string $prefix): bool
    {
        foreach (Elements::all($tree) as $element) {
            if (str_starts_with($element->name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function hasAttribute(Node $tree, string $attribute): bool
    {
        foreach (Elements::all($tree) as $element) {
            if ($element->attribute($attribute) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an element of the given tag carries the attribute with a dynamic
     * ({@code {expression}}) value — e.g. `<img src="{file.url}">`.
     */
    public static function hasDynamicAttribute(Node $tree, string $tag, string $attribute): bool
    {
        foreach (Elements::all($tree) as $element) {
            if ($element->name !== $tag) {
                continue;
            }

            $value = $element->attribute($attribute);
            if ($value !== null && str_contains($value, '{')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the inline Fluid snippet appears anywhere in the template's text or
     * attribute values (case-insensitive).
     */
    public static function inlineContains(Node $tree, string $needle): bool
    {
        return self::scan($tree, strtolower($needle));
    }

    private static function scan(Node $node, string $needle): bool
    {
        if ($node->type === NodeType::Text && str_contains(strtolower($node->text), $needle)) {
            return true;
        }

        if ($node->isElement()) {
            foreach ($node->attributes as $value) {
                if (str_contains(strtolower($value), $needle)) {
                    return true;
                }
            }
        }

        foreach ($node->children() as $child) {
            if (self::scan($child, $needle)) {
                return true;
            }
        }

        return false;
    }
}
