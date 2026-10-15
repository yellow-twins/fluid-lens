<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Index;

use YellowTwins\FluidLens\Template\ParsedTemplate;

/**
 * A component that already exists: its definition file and how often it is used.
 */
final class ExistingComponent
{
    public function __construct(
        public readonly string $name,
        public readonly ParsedTemplate $definition,
        public readonly int $usages,
    ) {
    }
}
