<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Consistency;

/**
 * One detected variant (a slider library, an icon set, …) and the files using it.
 */
final class Usage
{
    /**
     * @param list<string> $files
     */
    public function __construct(
        public readonly string $label,
        public readonly array $files,
    ) {
    }

    public function fileCount(): int
    {
        return count($this->files);
    }
}
