<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Config;

/**
 * The resolved project configuration. Every value is optional: a missing value
 * means "fall back to the command's built-in default", and any value here is in
 * turn overridden by an explicit command-line option.
 */
final class Config
{
    /**
     * @param list<string> $paths
     * @param list<string> $lintOnly
     * @param list<string> $lintExclude
     */
    public function __construct(
        public readonly array $paths = [],
        public readonly array $lintOnly = [],
        public readonly array $lintExclude = [],
        public readonly ?string $lintFailOn = null,
        public readonly ?string $lintBaseline = null,
        public readonly ?int $cloneMinElements = null,
        public readonly ?int $cloneMinOccurrences = null,
        public readonly ?string $baseline = null,
        public readonly ?float $similarThreshold = null,
        public readonly ?int $similarMinElements = null,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }
}
