<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Baseline;

use YellowTwins\FluidLens\Consistency\ConsistencyResult;
use YellowTwins\FluidLens\Consistency\Usage;

/**
 * A record of the consistency state a project already accepts, so an established
 * codebase can adopt the `consistency` command and only fail on *new* drift.
 *
 * Each check is stored with the set of variants in use when the baseline was
 * generated. A run stays green while a check's variants are all already known;
 * it fails as soon as a variant appears that the baseline does not list — which
 * covers both a third library joining an already-mixed check and a previously
 * consistent check becoming mixed.
 */
final class ConsistencyBaseline
{
    private const VERSION = 1;

    /**
     * @param array<string, list<string>> $checks check name => accepted variant labels
     */
    public function __construct(
        private readonly array $checks,
    ) {
    }

    /**
     * @param list<ConsistencyResult> $results
     */
    public static function fromResults(array $results): self
    {
        $checks = [];
        foreach ($results as $result) {
            if ($result->usages === []) {
                continue;
            }

            $labels = array_map(static fn (Usage $usage): string => $usage->label, $result->usages);
            sort($labels);
            $checks[$result->check] = $labels;
        }

        return new self($checks);
    }

    public static function fromJson(string $json): self
    {
        /** @var array{checks?: array<string, list<string>>} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self($data['checks'] ?? []);
    }

    public function toJson(): string
    {
        return json_encode(
            ['version' => self::VERSION, 'checks' => $this->checks],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function count(): int
    {
        return count($this->checks);
    }

    /**
     * Keeps only the results still worth acting on: a check that mixes variants
     * and whose current variants are not all covered by the baseline.
     *
     * @param list<ConsistencyResult> $results
     *
     * @return list<ConsistencyResult>
     */
    public function filter(array $results): array
    {
        return array_values(
            array_filter($results, fn (ConsistencyResult $result): bool => $this->isActionable($result)),
        );
    }

    private function isActionable(ConsistencyResult $result): bool
    {
        if (!$result->isInconsistent()) {
            return false;
        }

        $accepted = $this->checks[$result->check] ?? [];
        foreach ($result->usages as $usage) {
            if (!in_array($usage->label, $accepted, true)) {
                return true;
            }
        }

        return false;
    }
}
