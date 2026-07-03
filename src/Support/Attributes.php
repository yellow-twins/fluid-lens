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
        $value = $node->attribute($name);

        return $value !== null && trim($value) !== '';
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
