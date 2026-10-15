<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Component\Index\ExistingComponent;
use YellowTwins\FluidLens\Component\Model\ArgumentProposal;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Component\Model\ComponentCandidate;
use YellowTwins\FluidLens\Component\Model\ComponentReport;

/**
 * Prints component suggestions for people: one block per candidate with the
 * reasons, the caveats, the proposed arguments, a draft and the call replacing
 * each occurrence — followed by what stays a Partial and why.
 */
final class ConsoleComponentReporter
{
    private const INDENT = '     ';
    private const MAX_OCCURRENCES = 8;
    private const MAX_EXISTING = 8;

    public function __construct(
        private readonly int $limit = 20,
        private readonly bool $showDrafts = true,
    ) {
    }

    public function report(SymfonyStyle $io, ComponentReport $report): void
    {
        if ($report->candidates === []) {
            $io->success(sprintf('No component candidates in %d template(s).', $report->filesScanned));
        } else {
            $io->title('Component candidates');
            foreach (array_slice($report->candidates, 0, $this->limit) as $index => $candidate) {
                $this->candidate($io, $index + 1, $candidate);
            }
        }

        $this->kept($io, $report);
        $this->existing($io, $report);
        $this->partialsWithoutCandidates($io, $report);
        $this->summary($io, $report);
    }

    private function candidate(SymfonyStyle $io, int $number, ComponentCandidate $candidate): void
    {
        $io->writeln(sprintf(
            ' <fg=yellow;options=bold>#%d</>  <options=bold>%s</>  <fg=cyan><%s></>   score %s (%s)',
            $number,
            $candidate->name,
            $candidate->tag,
            number_format($candidate->score->value, 1),
            $candidate->score->priority(),
        ));
        $io->writeln(self::INDENT . sprintf(
            '<fg=gray>%s · %d call site(s) in %d file(s) · %d element(s)</>',
            implode(' + ', array_map(static fn (CandidateSource $s): string => $s->label(), $candidate->sources)),
            $candidate->callSites(),
            $candidate->fileCount(),
            $candidate->elementCount,
        ));

        $this->bullets($io, 'Why', $candidate->reasons);
        $this->bullets($io, 'Caveats', array_map(
            static fn ($note): string => sprintf('%s: %s', $note->code, $note->message),
            $candidate->caveats,
        ));
        if ($candidate->existingMatch !== null) {
            $this->bullets($io, 'Existing component', [sprintf(
                'looks like <%s> (%d%% similar) — consider using it instead',
                $candidate->existingMatch->tag,
                (int) round($candidate->existingMatch->similarity * 100),
            )]);
        }

        $this->arguments($io, $candidate->arguments);

        if ($candidate->slot !== null) {
            $location = OutputFormatter::escape($candidate->slot->location);
            $io->writeln(self::INDENT . '<options=bold>Slot</>  ' . $location);
            foreach ($candidate->slot->variants as $variant) {
                $io->writeln(self::INDENT . '  <fg=gray>' . OutputFormatter::escape($variant) . '</>');
            }
        }

        if ($this->showDrafts && $candidate->draft !== null) {
            $io->writeln(self::INDENT . '<options=bold>Draft</>  ' . OutputFormatter::escape($candidate->draft->path));
            $this->code($io, $candidate->draft->source);
        }

        $first = $candidate->occurrences[0] ?? null;
        if ($first !== null && $first->call !== '') {
            $location = OutputFormatter::escape($first->location());
            $io->writeln(self::INDENT . '<options=bold>Call</>  <fg=gray>(' . $location . ')</>');
            $this->code($io, $first->call);
        }

        $io->writeln(self::INDENT . '<options=bold>Occurrences</>');
        foreach (array_slice($candidate->occurrences, 0, self::MAX_OCCURRENCES) as $occurrence) {
            $io->writeln(self::INDENT . '  ' . OutputFormatter::escape($occurrence->location()));
        }
        $more = count($candidate->occurrences) - self::MAX_OCCURRENCES;
        if ($more > 0) {
            $io->writeln(self::INDENT . sprintf('  <fg=gray>… and %d more</>', $more));
        }
        $io->newLine();
    }

    /**
     * @param list<string> $items
     */
    private function bullets(SymfonyStyle $io, string $heading, array $items): void
    {
        if ($items === []) {
            return;
        }

        $io->writeln(self::INDENT . '<options=bold>' . $heading . '</>');
        foreach ($items as $item) {
            $io->writeln(self::INDENT . '  - ' . OutputFormatter::escape($item));
        }
    }

    /**
     * @param list<ArgumentProposal> $arguments
     */
    private function arguments(SymfonyStyle $io, array $arguments): void
    {
        if ($arguments === []) {
            $io->writeln(self::INDENT . '<options=bold>Arguments</>  <fg=gray>none</>');

            return;
        }

        $io->writeln(self::INDENT . '<options=bold>Arguments</>');
        $style = (new TableStyle())
            ->setHorizontalBorderChars('')
            ->setVerticalBorderChars(' ')
            ->setDefaultCrossingChar('')
            ->setCellHeaderFormat('<fg=gray>%s</>');

        $table = new Table($io);
        $table->setStyle($style);
        $table->setHeaders(['      name', 'type', 'optional', 'default', 'from', 'seen']);
        foreach ($arguments as $argument) {
            $table->addRow([
                '      ' . $argument->name,
                $argument->typeName(),
                $argument->optional ? 'yes' : 'no',
                $argument->default === null ? '—' : ($argument->default === '' ? '""' : $argument->default),
                $argument->origin->value,
                OutputFormatter::escape($this->shorten(implode(' | ', $argument->observedValues), 60)),
            ]);
        }
        $table->render();
    }

    private function code(SymfonyStyle $io, string $source): void
    {
        foreach (explode("\n", rtrim($source)) as $line) {
            $io->writeln(self::INDENT . '  <fg=green>' . OutputFormatter::escape($line) . '</>');
        }
    }

    private function kept(SymfonyStyle $io, ComponentReport $report): void
    {
        if ($report->keptPartials === []) {
            return;
        }

        $io->section('Kept as Partials (not suggested)');
        foreach ($report->keptPartials as $kept) {
            $io->writeln(sprintf(
                ' <options=bold>%s</>  <fg=gray>%d call site(s)</>',
                OutputFormatter::escape($kept->name),
                $kept->callSites,
            ));
            foreach ($kept->reasons as $reason) {
                $io->writeln(sprintf('   - %s: it %s', $reason->value, OutputFormatter::escape($reason->message())));
            }
        }
        $io->newLine();
    }

    private function existing(SymfonyStyle $io, ComponentReport $report): void
    {
        if ($report->existingComponents === []) {
            return;
        }

        $io->section(sprintf('Existing components (%d)', count($report->existingComponents)));
        $listed = array_map(
            static fn (ExistingComponent $component): string => sprintf('%s ×%d', $component->name, $component->usages),
            array_slice($report->existingComponents, 0, self::MAX_EXISTING),
        );
        $more = count($report->existingComponents) - self::MAX_EXISTING;
        $io->writeln(' ' . implode(', ', $listed) . ($more > 0 ? sprintf(', … and %d more', $more) : ''));
        $io->newLine();
    }

    private function partialsWithoutCandidates(SymfonyStyle $io, ComponentReport $report): void
    {
        if ($report->unreferencedPartials !== []) {
            $io->section('Partials without a render call in the scanned paths');
            $io->writeln(' <fg=gray>Overrides of third-party Partials, or unused — not judged here.</>');
            foreach ($report->unreferencedPartials as $partial) {
                $io->writeln(' ' . OutputFormatter::escape($partial->name));
            }
            $io->newLine();
        }

        if ($report->unresolvedPartials !== []) {
            $io->section('Render calls without a matching scanned Partial');
            foreach ($report->unresolvedPartials as $unresolved) {
                $io->writeln(sprintf(
                    ' %s  <fg=gray>%s, %d call(s)</>',
                    OutputFormatter::escape($unresolved->name),
                    $unresolved->reason,
                    count($unresolved->callers),
                ));
            }
            $io->newLine();
        }
    }

    private function summary(SymfonyStyle $io, ComponentReport $report): void
    {
        $io->section('Summary');
        $shown = min($this->limit, count($report->candidates));
        $io->writeln(sprintf(
            ' <options=bold>%d</> candidate(s)%s, <options=bold>%d</> kept as Partials,'
                . ' %d repetition(s) below the thresholds, %d template(s) scanned.',
            count($report->candidates),
            $shown < count($report->candidates) ? sprintf(' (top %d shown, --limit)', $shown) : '',
            count($report->keptPartials),
            $report->skippedLowPayoff,
            $report->filesScanned,
        ));
        if ($report->candidates !== []) {
            $io->writeln(' Nothing was written. Copy a draft into place, then replace each occurrence with its call.');
        }
        $io->newLine();
    }

    private function shorten(string $value, int $length): string
    {
        return strlen($value) > $length ? substr($value, 0, $length - 3) . '...' : $value;
    }
}
