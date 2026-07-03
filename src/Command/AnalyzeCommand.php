<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Baseline\Baseline;
use YellowTwins\FluidLens\Detector\CloneDetector;
use YellowTwins\FluidLens\Report\ConsoleCloneReporter;
use YellowTwins\FluidLens\Report\JsonCloneReporter;
use YellowTwins\FluidLens\Template\TemplateCollection;
use YellowTwins\FluidLens\Template\TemplateCollector;

/**
 * Analyses a path of Fluid templates for duplicated structures that should be
 * extracted into reusable Partials.
 *
 * Exits non-zero when duplicates are found, so it can gate a CI pipeline.
 */
#[AsCommand(
    name: 'analyze',
    description: 'Find duplicated markup structures across Fluid templates.',
)]
final class AnalyzeCommand extends Command
{
    private const DEFAULT_BASELINE = 'fluid-lens-baseline.json';

    public function __construct(
        private readonly TemplateCollector $collector = new TemplateCollector(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'A template file or a directory to scan recursively.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output findings as JSON instead of a report.')
            ->addOption(
                'min-elements',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum elements a structure must have to be reported.',
                '3',
            )
            ->addOption(
                'min-occurrences',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum times a structure must repeat to be reported.',
                '2',
            )
            ->addOption(
                'baseline',
                null,
                InputOption::VALUE_REQUIRED,
                'Suppress duplicates recorded in this baseline file.',
            )
            ->addOption(
                'generate-baseline',
                null,
                InputOption::VALUE_OPTIONAL,
                'Write the current duplicates to a baseline file and exit.',
                false,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $collection = $this->collector->collect((string) $input->getArgument('path'));

        if ($collection->isEmpty()) {
            $io->error(sprintf('No Fluid templates found at: %s', $input->getArgument('path')));

            return Command::FAILURE;
        }

        $this->warnAboutSkipped($io, $collection);

        $groups = $this->createDetector($input)->detect($collection->templates);

        if ($this->isGeneratingBaseline($input)) {
            return $this->writeBaseline($io, $input, $groups);
        }

        $groups = $this->applyBaseline($io, $input, $groups);
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonCloneReporter())->render($groups, $fileCount));
        } else {
            (new ConsoleCloneReporter())->report($io, $groups, $fileCount);
        }

        return $groups === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function createDetector(InputInterface $input): CloneDetector
    {
        return new CloneDetector(
            max(1, (int) $input->getOption('min-elements')),
            max(2, (int) $input->getOption('min-occurrences')),
        );
    }

    private function isGeneratingBaseline(InputInterface $input): bool
    {
        return $input->getOption('generate-baseline') !== false;
    }

    /**
     * @param list<\YellowTwins\FluidLens\Detector\CloneGroup> $groups
     */
    private function writeBaseline(SymfonyStyle $io, InputInterface $input, array $groups): int
    {
        $value = $input->getOption('generate-baseline');
        $path = is_string($value) && $value !== '' ? $value : self::DEFAULT_BASELINE;

        if (file_put_contents($path, Baseline::fromGroups($groups)->toJson()) === false) {
            $io->error(sprintf('Could not write baseline to: %s', $path));

            return Command::FAILURE;
        }

        $io->success(sprintf('Wrote baseline with %d structure(s) to %s.', count($groups), $path));

        return Command::SUCCESS;
    }

    /**
     * @param list<\YellowTwins\FluidLens\Detector\CloneGroup> $groups
     *
     * @return list<\YellowTwins\FluidLens\Detector\CloneGroup>
     */
    private function applyBaseline(SymfonyStyle $io, InputInterface $input, array $groups): array
    {
        $path = $input->getOption('baseline');
        if (!is_string($path)) {
            return $groups;
        }

        if (!is_file($path)) {
            $io->warning(sprintf('Baseline file not found, reporting everything: %s', $path));

            return $groups;
        }

        return Baseline::fromJson((string) file_get_contents($path))->filter($groups);
    }

    private function warnAboutSkipped(SymfonyStyle $io, TemplateCollection $collection): void
    {
        foreach ($collection->skipped as $skip) {
            $io->warning(sprintf('Skipped %s: %s', $skip['file'], $skip['reason']));
        }
    }
}
