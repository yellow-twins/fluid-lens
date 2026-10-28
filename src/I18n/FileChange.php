<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\I18n;

/**
 * A file's content before and after extraction; {@see $before} is null for a
 * file that does not exist yet.
 */
final class FileChange
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $before,
        public readonly string $after,
    ) {
    }

    public function isNew(): bool
    {
        return $this->before === null;
    }
}
