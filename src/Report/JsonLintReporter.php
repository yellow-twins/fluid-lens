<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Rule\Finding;

/**
 * Serialises lint findings to stable, machine-readable JSON.
 */
final class JsonLintReporter
{
    /**
     * @param list<Finding> $findings
     */
    public function render(array $findings, int $filesScanned): string
    {
        $payload = [
            'filesScanned' => $filesScanned,
            'findingCount' => count($findings),
            'findings' => array_map(fn (Finding $finding): array => $this->finding($finding), $findings),
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(Finding $finding): array
    {
        return [
            'rule' => $finding->rule,
            'severity' => $finding->severity->value,
            'message' => $finding->message,
            'file' => $finding->file,
            'line' => $finding->line,
            'reference' => $finding->reference,
        ];
    }
}
