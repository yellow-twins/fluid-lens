<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Consistency\ConsistencyResult;
use YellowTwins\FluidLens\Consistency\Usage;

/**
 * Serialises the consistency-check results to stable, machine-readable JSON.
 */
final class JsonConsistencyReporter
{
    /**
     * @param list<ConsistencyResult> $results
     */
    public function render(array $results, int $filesScanned): string
    {
        $consistent = array_filter($results, static fn (ConsistencyResult $r): bool => $r->isInconsistent()) === [];

        $payload = [
            'filesScanned' => $filesScanned,
            'consistent' => $consistent,
            'checks' => array_map(fn (ConsistencyResult $result): array => $this->result($result), $results),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function result(ConsistencyResult $result): array
    {
        return [
            'check' => $result->check,
            'title' => $result->title,
            'inconsistent' => $result->isInconsistent(),
            'variants' => array_map(
                static fn (Usage $usage): array => [
                    'label' => $usage->label,
                    'fileCount' => $usage->fileCount(),
                    'files' => $usage->files,
                ],
                $result->usages,
            ),
        ];
    }
}
