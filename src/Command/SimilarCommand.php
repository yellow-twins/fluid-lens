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
use YellowTwins\FluidLens\Detector\NearDuplicateDetector;
use YellowTwins\FluidLens\Report\ConsoleNearDuplicateReporter;
use YellowTwins\FluidLens\Report\JsonNearDuplicateReporter;
use YellowTwins\FluidLens\Template\TemplateCollection;
use YellowTwins\FluidLens\Template\TemplateCollector;

/**
 * Analyses a path of Fluid templates for near-duplicate structures — blocks that
 * are almost, but not quite, identical and could share a single Partial.
 *
 * Exits non-zero when near-duplicates are found, so it can gate a CI pipeline.
 */
#[AsCommand(
    name: 'similar',
    description: 'Find near-duplicate markup structures across Fluid templates.',
)]
final class SimilarCommand extends Command
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
                'threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum similarity (0..1) for two structures to be linked.',
                '0.8',
            )
            ->addOption(
                'min-elements',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum elements a structure must have to be considered.',
                '4',
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

        $detector = new NearDuplicateDetector(
            $this->threshold($input),
            max(2, (int) $input->getOption('min-elements')),
        );
        $clusters = $detector->detect($collection->templates);
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonNearDuplicateReporter())->render($clusters, $fileCount));
        } else {
            (new ConsoleNearDuplicateReporter())->report($io, $clusters, $fileCount);
        }

        return $clusters === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function threshold(InputInterface $input): float
    {
        return max(0.1, min(1.0, (float) $input->getOption('threshold')));
    }

    private function warnAboutSkipped(SymfonyStyle $io, TemplateCollection $collection): void
    {
        foreach ($collection->skipped as $skip) {
            $io->warning(sprintf('Skipped %s: %s', $skip['file'], $skip['reason']));
        }
    }
}
