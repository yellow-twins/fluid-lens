<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Component;

use YellowTwins\FluidLens\Component\Model\CandidateScore;

/**
 * Ranks suggestions by how much a component would save.
 *
 * The value grows with the number of call sites and, more slowly, with the size
 * of the markup; spreading over several files counts extra (that is where copies
 * drift apart). Every argument makes a component a little harder to use, and
 * beyond the configured maximum it is probably two components in one.
 */
final class CandidateScorer
{
    private const SPREAD_PER_FILE = 0.5;
    private const MAX_SPREAD = 2.0;
    private const PENALTY_PER_ARGUMENT = 0.05;
    private const TOO_CONFIGURABLE = 0.5;

    public function __construct(
        private readonly int $maxArguments = 6,
    ) {
    }

    /**
     * @param array<string, float> $adjustments named extra multipliers (bonuses, penalties)
     */
    public function score(
        int $callSites,
        int $elementCount,
        int $files,
        int $arguments,
        array $adjustments = [],
    ): CandidateScore {
        $factors = [
            'call sites' => (float) $callSites,
            'size' => round(log(1 + max(1, $elementCount), 2), 2),
            'spread' => min(self::MAX_SPREAD, 1 + self::SPREAD_PER_FILE * max(0, $files - 1)),
            'arguments' => $this->argumentFactor($arguments),
        ] + $adjustments;

        $value = 1.0;
        foreach ($factors as $factor) {
            $value *= $factor;
        }

        return new CandidateScore(round($value, 1), $factors);
    }

    public function isTooConfigurable(int $arguments): bool
    {
        return $arguments > $this->maxArguments;
    }

    private function argumentFactor(int $arguments): float
    {
        if ($this->isTooConfigurable($arguments)) {
            return self::TOO_CONFIGURABLE;
        }

        return round(1 - self::PENALTY_PER_ARGUMENT * $arguments, 2);
    }
}
