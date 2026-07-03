<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Rule;

/**
 * A single problem a rule reported at a specific place in a template.
 */
final class Finding
{
    public function __construct(
        public readonly string $rule,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $reference = null,
    ) {
    }
}
