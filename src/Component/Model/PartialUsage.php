<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * The Partial(s) a suggestion replaces.
 */
final class PartialUsage
{
    /**
     * @param list<string> $names partial names as rendered, e.g. "Event/Teaser/Header"
     * @param list<string> $files
     */
    public function __construct(
        public readonly array $names,
        public readonly array $files,
    ) {
    }
}
