<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

/**
 * One slider library detected in the project, and the files that use it.
 */
final class SliderUsage
{
    /**
     * @param list<string> $files
     */
    public function __construct(
        public readonly string $library,
        public readonly array $files,
    ) {
    }

    public function fileCount(): int
    {
        return count($this->files);
    }
}
