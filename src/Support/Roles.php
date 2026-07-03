<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

use YellowTwins\FluidLens\Parser\Node;

/**
 * Helper for reading an element's WAI-ARIA `role`, which may list several
 * fallback roles separated by whitespace.
 */
final class Roles
{
    public static function has(Node $node, string $role): bool
    {
        foreach (preg_split('/\s+/', trim($node->attribute('role') ?? '')) ?: [] as $token) {
            if ($token === $role) {
                return true;
            }
        }

        return false;
    }
}
