<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Resolves the attributes an element really renders.
 *
 * TYPO3's tag-based ViewHelpers expand three array arguments into attributes:
 * {@code aria="{label: '…'}"} becomes `aria-label`, {@code data="{…}"} becomes
 * `data-*`, and {@code additionalAttributes="{…}"} is written out verbatim. The
 * parser only ever sees the unexpanded argument, so resolving it in one place
 * spares every rule from knowing about the Fluid syntax.
 */
final class TagAttributes
{
    /**
     * Argument name to the prefix it expands to. The names are lowercase
     * because the HTML5 tokenizer lowercases every attribute name.
     */
    private const EXPANDING_ARGUMENTS = [
        'aria' => 'aria-',
        'data' => 'data-',
        'additionalattributes' => '',
    ];

    /**
     * The element's attributes with the expanded arguments merged in.
     *
     * @return array<string, string>
     */
    public static function resolve(Node $node): array
    {
        $attributes = $node->attributes;

        foreach (self::EXPANDING_ARGUMENTS as $argument => $prefix) {
            foreach (self::expansion($node, $argument) as $key => $value) {
                // An attribute written out explicitly always wins over the array form.
                $attributes[$prefix . strtolower($key)] ??= $value;
            }
        }

        return $attributes;
    }

    public static function value(Node $node, string $name): ?string
    {
        return self::resolve($node)[$name] ?? null;
    }

    /**
     * Whether an expanding argument carries an expression that cannot be read
     * statically, such as {@code aria="{ariaAttributes}"}. Rules use this to
     * stay silent rather than report something they cannot see.
     */
    public static function hasOpaqueArgument(Node $node): bool
    {
        foreach (array_keys(self::EXPANDING_ARGUMENTS) as $argument) {
            $value = $node->attribute($argument);
            if (Attributes::isDynamic($value) && FluidArray::parse((string) $value) === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private static function expansion(Node $node, string $argument): array
    {
        $value = $node->attribute($argument);
        if ($value === null) {
            return [];
        }

        return FluidArray::parse($value) ?? [];
    }
}
