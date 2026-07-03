<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Detector\NearDuplicateCluster;
use YellowTwins\FluidLens\Detector\NearDuplicateMember;

/**
 * Prints near-duplicate clusters as a readable report for humans.
 */
final class ConsoleNearDuplicateReporter
{
    private readonly StructurePreview $preview;

    public function __construct(?StructurePreview $preview = null)
    {
        $this->preview = $preview ?? new StructurePreview();
    }

    /**
     * @param list<NearDuplicateCluster> $clusters
     */
    public function report(SymfonyStyle $io, array $clusters, int $filesScanned): void
    {
        if ($clusters === []) {
            $io->success(sprintf('No near-duplicate structures found in %d template(s).', $filesScanned));

            return;
        }

        $io->title('Near-duplicate structures');

        foreach ($clusters as $index => $cluster) {
            $this->reportCluster($io, $index + 1, $cluster);
        }

        $io->section('Summary');
        $io->writeln(sprintf(
            ' <options=bold>%d</> near-duplicate cluster(s) across <options=bold>%d</> template(s).',
            count($clusters),
            $filesScanned,
        ));
        $io->writeln(' Each cluster could become one Partial, with the differences passed as arguments.');
        $io->newLine();
    }

    private function reportCluster(SymfonyStyle $io, int $number, NearDuplicateCluster $cluster): void
    {
        $io->writeln(sprintf(
            ' <fg=yellow;options=bold>#%d</>  <options=bold>%d variants</>, %d%% similar, %d occurrence(s) total',
            $number,
            $cluster->memberCount(),
            (int) round($cluster->similarity * 100),
            $cluster->totalOccurrences(),
        ));
        $io->newLine();

        foreach ($cluster->members as $letter => $member) {
            $this->reportMember($io, chr(ord('A') + (int) $letter), $member);
        }
    }

    private function reportMember(SymfonyStyle $io, string $label, NearDuplicateMember $member): void
    {
        $io->writeln(sprintf(
            '      <fg=green;options=bold>Variant %s</> <fg=gray>(%d elements, %d occurrence(s))</>',
            $label,
            $member->elementCount,
            $member->occurrenceCount(),
        ));

        foreach ($this->preview->render($member->representative) as $line) {
            $io->writeln('        <fg=cyan>' . OutputFormatter::escape($line) . '</>');
        }

        foreach ($member->occurrences as $occurrence) {
            $io->writeln(sprintf('          <fg=gray>%s:%d</>', $occurrence->file, $occurrence->line));
        }
        $io->newLine();
    }
}
