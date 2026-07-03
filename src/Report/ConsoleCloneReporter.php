<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Detector\CloneGroup;

/**
 * Prints clone groups as a readable, PHPStan-style report for humans.
 */
final class ConsoleCloneReporter
{
    private readonly StructurePreview $preview;

    public function __construct(?StructurePreview $preview = null)
    {
        $this->preview = $preview ?? new StructurePreview();
    }

    /**
     * @param list<CloneGroup> $groups
     */
    public function report(SymfonyStyle $io, array $groups, int $filesScanned): void
    {
        if ($groups === []) {
            $io->success(sprintf('No duplicated structures found in %d template(s).', $filesScanned));

            return;
        }

        $io->title('Duplicated structures');

        foreach ($groups as $index => $group) {
            $this->reportGroup($io, $index + 1, $group);
        }

        $io->section('Summary');
        $io->writeln(sprintf(
            ' <options=bold>%d</> duplicated structure(s) across <options=bold>%d</> template(s).',
            count($groups),
            $filesScanned,
        ));
        $io->writeln(' Consider extracting each into a Partial and passing the differences as arguments.');
        $io->newLine();
    }

    private function reportGroup(SymfonyStyle $io, int $number, CloneGroup $group): void
    {
        $io->writeln(sprintf(
            ' <fg=yellow;options=bold>#%d</>  <options=bold>%d occurrences</>, %d elements each',
            $number,
            $group->occurrenceCount(),
            $group->elementCount,
        ));

        $io->newLine();
        foreach ($this->preview->render($group->representative()) as $line) {
            $io->writeln('      <fg=cyan>' . OutputFormatter::escape($line) . '</>');
        }

        $io->newLine();
        $io->writeln('      <fg=gray>Occurrences:</>');
        foreach ($group->occurrences as $occurrence) {
            $io->writeln(sprintf('        %s<fg=gray>:%d</>', $occurrence->file, $occurrence->line));
        }
        $io->newLine();
    }
}
