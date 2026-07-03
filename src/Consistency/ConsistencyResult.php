<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

/**
 * The outcome of one consistency check: the variants it found and where.
 * More than one variant means the project mixes competing implementations.
 */
final class ConsistencyResult
{
    /**
     * @param list<Usage> $usages
     */
    public function __construct(
        public readonly string $check,
        public readonly string $title,
        public readonly array $usages,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->usages === [];
    }

    public function isInconsistent(): bool
    {
        return count($this->usages) > 1;
    }
}
