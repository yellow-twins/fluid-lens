<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Consistency\ConsistencyResult;
use YellowTwins\FluidLens\Consistency\Usage;

/**
 * Prints the outcome of the consistency checks, one section each, flagging the
 * checks where the project mixes competing implementations.
 */
final class ConsoleConsistencyReporter
{
    private const MAX_FILES = 5;

    /**
     * @param list<ConsistencyResult> $results
     */
    public function report(SymfonyStyle $io, array $results, int $filesScanned): void
    {
        $io->title('Consistency');

        foreach ($results as $result) {
            $this->reportResult($io, $result);
        }

        $inconsistent = array_filter($results, static fn (ConsistencyResult $r): bool => $r->isInconsistent());
        if ($inconsistent === []) {
            $io->success(sprintf('Consistent across %d template(s).', $filesScanned));
        } else {
            $io->warning(sprintf(
                '%d check(s) found mixed implementations — consider consolidating.',
                count($inconsistent),
            ));
        }
    }

    private function reportResult(SymfonyStyle $io, ConsistencyResult $result): void
    {
        $io->writeln(sprintf(' <options=bold>%s</>', $result->title));

        if ($result->isEmpty()) {
            $io->writeln('   <fg=gray>none found</>');
            $io->newLine();

            return;
        }

        foreach ($result->usages as $usage) {
            $this->reportUsage($io, $usage);
        }

        $io->writeln($result->isInconsistent()
            ? sprintf('   <fg=yellow>mixed: %d different in use — consolidate</>', count($result->usages))
            : '   <fg=green>consistent</>');
        $io->newLine();
    }

    private function reportUsage(SymfonyStyle $io, Usage $usage): void
    {
        $io->writeln(sprintf('   %s <fg=gray>(%d file(s))</>', $usage->label, $usage->fileCount()));
        foreach (array_slice($usage->files, 0, self::MAX_FILES) as $file) {
            $io->writeln('     <fg=gray>' . $file . '</>');
        }
        if ($usage->fileCount() > self::MAX_FILES) {
            $io->writeln(sprintf('     <fg=gray>… and %d more</>', $usage->fileCount() - self::MAX_FILES));
        }
    }
}
