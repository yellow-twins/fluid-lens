<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Fluid;

/**
 * How a subtree uses one variable: the paths read from it and in which roles.
 */
final class VariableUsage
{
    public const HINT_CONDITION = 'condition';
    public const HINT_EACH = 'each';
    public const HINT_OUTPUT = 'output';

    /**
     * @param list<string> $paths full paths read, e.g. ['settings.listPageUid']
     * @param list<string> $hints the roles it appears in (see HINT_*)
     */
    public function __construct(
        public readonly string $root,
        public readonly array $paths,
        public readonly array $hints,
    ) {
    }

    public function hasHint(string $hint): bool
    {
        return in_array($hint, $this->hints, true);
    }

    /**
     * Whether a property of the variable is read ({@code {item.title}}), which
     * marks it as an object or array rather than a plain value.
     */
    public function isAccessedByPath(): bool
    {
        foreach ($this->paths as $path) {
            if ($path !== $this->root) {
                return true;
            }
        }

        return false;
    }
}
