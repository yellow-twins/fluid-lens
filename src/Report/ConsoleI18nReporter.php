<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\I18n\ExtractionPlan;
use YellowTwins\FluidLens\I18n\PlannedText;
use YellowTwins\FluidLens\Support\Paths;

/**
 * Prints hardcoded texts grouped by template: for each one the key and language
 * file it would move to, or why it has to be moved by hand.
 */
final class ConsoleI18nReporter
{
    private const MAX_TEXT = 48;

    public function report(SymfonyStyle $io, ExtractionPlan $plan, int $filesScanned): void
    {
        if ($plan->texts === []) {
            $io->success(sprintf('No hardcoded text found in %d template(s).', $filesScanned));

            return;
        }

        $io->title('Hardcoded texts');
        foreach ($this->groupByFile($plan->texts) as $file => $texts) {
            $this->reportFile($io, $file, $texts);
        }

        $this->printSummary($io, $plan, $filesScanned);
    }

    /**
     * @param list<PlannedText> $texts
     *
     * @return array<string, list<PlannedText>>
     */
    private function groupByFile(array $texts): array
    {
        $grouped = [];
        foreach ($texts as $text) {
            $grouped[$text->text->file][] = $text;
        }

        return $grouped;
    }

    /**
     * @param list<PlannedText> $texts
     */
    private function reportFile(SymfonyStyle $io, string $file, array $texts): void
    {
        $io->writeln(sprintf(' <options=bold>%s</>', OutputFormatter::escape(Paths::relative($file))));

        $languageFiles = array_unique(array_filter(array_map(
            static fn (PlannedText $text): ?string => $text->isExtractable() ? $text->target?->file : null,
            $texts,
        )));
        foreach ($languageFiles as $languageFile) {
            $io->writeln(sprintf('   <fg=gray>→ %s</>', OutputFormatter::escape(Paths::relative($languageFile))));
        }

        $contextWidth = max(array_map(static fn (PlannedText $text): int => mb_strlen($text->text->context), $texts));
        $textWidth = max(array_map(fn (PlannedText $text): int => mb_strlen($this->quote($text->text->text)), $texts));
        foreach ($texts as $text) {
            $io->writeln(sprintf(
                '   <fg=gray>%4d</>  %s  %s  %s',
                $text->text->line,
                OutputFormatter::escape($this->pad($text->text->context, $contextWidth)),
                OutputFormatter::escape($this->pad($this->quote($text->text->text), $textWidth)),
                $this->outcome($text),
            ));
        }
        $io->newLine();
    }

    private function outcome(PlannedText $text): string
    {
        if ($text->isExtractable()) {
            return sprintf(
                '<fg=green>%s</>%s',
                OutputFormatter::escape((string) $text->key),
                $text->reusesKey() ? ' <fg=gray>(existing)</>' : '',
            );
        }

        return sprintf('<fg=yellow>manual:</> %s', OutputFormatter::escape((string) $text->blocker()));
    }

    private function pad(string $text, int $width): string
    {
        return $text . str_repeat(' ', max(0, $width - mb_strlen($text)));
    }

    private function quote(string $text): string
    {
        $short = mb_strlen($text) > self::MAX_TEXT ? mb_substr($text, 0, self::MAX_TEXT - 1) . '…' : $text;

        return '"' . $short . '"';
    }

    private function printSummary(SymfonyStyle $io, ExtractionPlan $plan, int $filesScanned): void
    {
        $files = count(array_unique(
            array_map(static fn (PlannedText $text): string => $text->text->file, $plan->texts),
        ));

        $io->section('Summary');
        $io->writeln(sprintf(
            ' %d text(s) can be moved to a language file, %d need manual work, in %d of %d template(s).',
            count($plan->extractable()),
            count($plan->manual()),
            $files,
            $filesScanned,
        ));
        if ($plan->extractable() !== []) {
            $io->writeln(' <fg=gray>Preview the changes with --fix --dry-run, apply them with --fix.</>');
        }
        $io->newLine();
    }
}
