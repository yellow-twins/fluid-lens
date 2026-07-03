<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * Prints lint findings grouped by file, in a readable phpcs-style report, and
 * honestly notes the accessibility criteria that a static check cannot decide.
 */
final class ConsoleLintReporter
{
    /**
     * @param list<Finding> $findings
     */
    public function report(SymfonyStyle $io, array $findings, int $filesScanned): void
    {
        if ($findings === []) {
            $io->success(sprintf('No issues found in %d template(s).', $filesScanned));
            $this->printRuntimeNote($io);

            return;
        }

        $io->title('Lint findings');
        foreach ($this->groupByFile($findings) as $file => $fileFindings) {
            $this->reportFile($io, $file, $fileFindings);
        }

        $this->printSummary($io, $findings, $filesScanned);
        $this->printRuntimeNote($io);
    }

    /**
     * @param list<Finding> $findings
     *
     * @return array<string, list<Finding>>
     */
    private function groupByFile(array $findings): array
    {
        $grouped = [];
        foreach ($findings as $finding) {
            $grouped[$finding->file][] = $finding;
        }

        return $grouped;
    }

    /**
     * @param list<Finding> $findings
     */
    private function reportFile(SymfonyStyle $io, string $file, array $findings): void
    {
        $io->writeln(sprintf(' <options=bold>%s</>', $file));
        foreach ($findings as $finding) {
            $io->writeln(sprintf(
                '   %s <fg=gray>%d</>  %s  <fg=gray>%s%s</>',
                $this->badge($finding->severity),
                $finding->line,
                OutputFormatter::escape($finding->message),
                $finding->rule,
                $finding->reference !== null ? ' · ' . $finding->reference : '',
            ));
        }
        $io->newLine();
    }

    /**
     * @param list<Finding> $findings
     */
    private function printSummary(SymfonyStyle $io, array $findings, int $filesScanned): void
    {
        $counts = ['error' => 0, 'warning' => 0, 'notice' => 0];
        foreach ($findings as $finding) {
            $counts[$finding->severity->value]++;
        }

        $io->section('Summary');
        $io->writeln(sprintf(
            ' %d error(s), %d warning(s), %d notice(s) across %d template(s).',
            $counts['error'],
            $counts['warning'],
            $counts['notice'],
            $filesScanned,
        ));
    }

    private function printRuntimeNote(SymfonyStyle $io): void
    {
        $io->writeln(
            ' <fg=gray>Note: colour contrast, runtime focus order and reflow cannot be checked</>',
        );
        $io->writeln(
            ' <fg=gray>statically — verify those with a runtime tool (axe, Lighthouse).</>',
        );
        $io->newLine();
    }

    private function badge(Severity $severity): string
    {
        return match ($severity) {
            Severity::Error => '<fg=red;options=bold>ERROR  </>',
            Severity::Warning => '<fg=yellow;options=bold>WARNING</>',
            Severity::Notice => '<fg=blue>NOTICE </>',
        };
    }
}
