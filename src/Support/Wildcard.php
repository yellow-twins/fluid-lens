<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Support;

/**
 * Matches a name against patterns that may use a single trailing `*`, so
 * {@code wcag.*} matches every name starting with {@code wcag.}.
 */
final class Wildcard
{
    /**
     * @param list<string> $patterns
     */
    public static function matchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($name, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function matches(string $name, string $pattern): bool
    {
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($name, substr($pattern, 0, -1));
        }

        return $name === $pattern;
    }
}
