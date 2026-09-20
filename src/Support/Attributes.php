<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Helpers for reasoning about attribute values, which in Fluid are often dynamic
 * ({@code {expressions}}) and therefore cannot be evaluated statically.
 */
final class Attributes
{
    /**
     * Whether the attribute is present with a non-empty value (dynamic values
     * such as {@code {title}} count as present).
     */
    public static function present(Node $node, string $name): bool
    {
        $value = self::value($node, $name);

        return $value !== null && trim($value) !== '';
    }

    /**
     * The attribute's effective value, including the attributes TYPO3 ViewHelpers
     * expand from their `aria`, `data` and `additionalAttributes` arguments.
     */
    public static function value(Node $node, string $name): ?string
    {
        return TagAttributes::value($node, $name);
    }

    /**
     * Whether the value contains a Fluid expression and so cannot be judged
     * statically.
     */
    public static function isDynamic(?string $value): bool
    {
        return $value !== null && str_contains($value, '{');
    }
}
