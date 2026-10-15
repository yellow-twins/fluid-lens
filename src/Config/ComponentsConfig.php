<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Config;

/**
 * The "components" section of the configuration. A null value falls back to
 * the command's default.
 */
final class ComponentsConfig
{
    /**
     * @param list<string> $only     signals to use: partials, clones, similar
     * @param list<string> $prefixes component namespace prefixes besides the detected ones
     */
    public function __construct(
        public readonly ?int $minOccurrences = null,
        public readonly ?int $minFiles = null,
        public readonly ?int $minElements = null,
        public readonly ?int $maxArguments = null,
        public readonly ?float $threshold = null,
        public readonly ?float $minShared = null,
        public readonly array $only = [],
        public readonly array $prefixes = [],
        public readonly ?string $baseline = null,
    ) {
    }
}
