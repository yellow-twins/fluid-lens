<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component\Model;

/**
 * How worthwhile a suggestion is, with the factors that produced the value so a
 * reader can see why one candidate ranks above another.
 */
final class CandidateScore
{
    private const HIGH = 12.0;
    private const MEDIUM = 6.0;

    /**
     * @param array<string, float> $factors factor name => multiplier
     */
    public function __construct(
        public readonly float $value,
        public readonly array $factors,
    ) {
    }

    public function priority(): string
    {
        return match (true) {
            $this->value >= self::HIGH => 'high',
            $this->value >= self::MEDIUM => 'medium',
            default => 'low',
        };
    }
}
