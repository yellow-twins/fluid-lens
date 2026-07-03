<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Baseline;

use YellowTwins\FluidLens\Rule\Finding;

/**
 * A record of already-known lint findings, so a project with existing
 * accessibility debt can adopt fluid-lens and only fail on *new* findings.
 *
 * A finding is identified by its rule, file and message — but not its line, so
 * edits that shift lines around do not churn the baseline. Recurring findings are
 * suppressed up to the count recorded; an additional one of the same kind, or a
 * finding in a new file, surfaces.
 */
final class LintBaseline
{
    private const VERSION = 1;

    /**
     * @param array<string, int> $findings identity => count
     */
    public function __construct(
        private readonly array $findings,
    ) {
    }

    /**
     * @param list<Finding> $findings
     */
    public static function fromFindings(array $findings): self
    {
        $counts = [];
        foreach ($findings as $finding) {
            $key = self::key($finding);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return new self($counts);
    }

    public static function fromJson(string $json): self
    {
        /** @var array{findings?: array<string, int>} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self($data['findings'] ?? []);
    }

    public function toJson(): string
    {
        return json_encode(
            ['version' => self::VERSION, 'findings' => $this->findings],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function count(): int
    {
        return array_sum($this->findings);
    }

    /**
     * Returns only the findings not already covered by the baseline.
     *
     * @param list<Finding> $findings
     *
     * @return list<Finding>
     */
    public function filter(array $findings): array
    {
        $remaining = $this->findings;
        $kept = [];

        foreach ($findings as $finding) {
            $key = self::key($finding);
            if (($remaining[$key] ?? 0) > 0) {
                $remaining[$key]--;
                continue;
            }

            $kept[] = $finding;
        }

        return $kept;
    }

    private static function key(Finding $finding): string
    {
        return $finding->rule . '|' . $finding->file . '|' . $finding->message;
    }
}
