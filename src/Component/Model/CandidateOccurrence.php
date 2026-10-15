<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * One place that would call the component, with the call that replaces it.
 */
final class CandidateOccurrence
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $call = '',
    ) {
    }

    public function location(): string
    {
        return $this->line > 0 ? sprintf('%s:%d', $this->file, $this->line) : $this->file;
    }
}
