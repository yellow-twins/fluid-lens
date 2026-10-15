<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * A ready-to-copy component file. Nothing is written: the path is a suggestion.
 */
final class ComponentDraft
{
    public function __construct(
        public readonly string $path,
        public readonly string $source,
    ) {
    }
}
