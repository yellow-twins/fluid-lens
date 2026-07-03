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

        $detector = new CloneDetector(
            max(1, (int) $input->getOption('min-elements')),
            max(2, (int) $input->getOption('min-occurrences')),
        );
        $groups = $detector->detect($collection->templates);
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonCloneReporter())->render($groups, $fileCount));
        } else {
            (new ConsoleCloneReporter())->report($io, $groups, $fileCount);
        }

        return $groups === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function warnAboutSkipped(SymfonyStyle $io, TemplateCollection $collection): void
    {
        foreach ($collection->skipped as $skip) {
            $io->warning(sprintf('Skipped %s: %s', $skip['file'], $skip['reason']));
        }
    }
}
