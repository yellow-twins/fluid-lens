<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Consistency\SliderUsage;

/**
 * Prints which slider libraries a project uses and flags a mix of several.
 */
final class ConsoleSliderReporter
{
    private const MAX_FILES = 5;

    /**
     * @param list<SliderUsage> $usages
     */
    public function report(SymfonyStyle $io, array $usages, int $filesScanned): void
    {
        if ($usages === []) {
            $io->success(sprintf('No known slider libraries found in %d template(s).', $filesScanned));

            return;
        }

        $io->title('Slider libraries');
        foreach ($usages as $usage) {
            $this->reportUsage($io, $usage);
        }

        if (count($usages) > 1) {
            $io->warning(sprintf(
                '%d different slider libraries in one project — consider consolidating to one.',
                count($usages),
            ));
        } else {
            $io->success('One slider library — consistent.');
        }

        $io->writeln(' <fg=gray>Tip: repeated slider markup is caught by `analyze` and `similar`.</>');
        $io->newLine();
    }

    private function reportUsage(SymfonyStyle $io, SliderUsage $usage): void
    {
        $io->writeln(sprintf(' <options=bold>%s</> <fg=gray>(%d file(s))</>', $usage->library, $usage->fileCount()));
        foreach (array_slice($usage->files, 0, self::MAX_FILES) as $file) {
            $io->writeln('   ' . $file);
        }
        if ($usage->fileCount() > self::MAX_FILES) {
            $io->writeln(sprintf('   <fg=gray>… and %d more</>', $usage->fileCount() - self::MAX_FILES));
        }
        $io->newLine();
    }
}
