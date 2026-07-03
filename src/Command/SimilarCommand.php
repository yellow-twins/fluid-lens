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
use YellowTwins\FluidLens\Config\OptionResolver;
use YellowTwins\FluidLens\Detector\NearDuplicateDetector;
use YellowTwins\FluidLens\Report\ConsoleNearDuplicateReporter;
use YellowTwins\FluidLens\Report\JsonNearDuplicateReporter;
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
    use ResolvesConfig;

    public function __construct(
        private readonly TemplateCollector $collector = new TemplateCollector(),
        private readonly OptionResolver $options = new OptionResolver(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'A file or directory (default: config paths).')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a fluid-lens.php config file.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output findings as JSON instead of a report.')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Minimum similarity 0..1 to link structures.')
            ->addOption('min-elements', null, InputOption::VALUE_REQUIRED, 'Minimum elements per structure.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->loadConfig($input);

        $paths = $this->resolvePaths($input, $config);
        if ($paths === []) {
            $io->error('No path given. Pass one, or set "paths" in fluid-lens.php.');

            return Command::FAILURE;
        }

        $collection = $this->collector->collectPaths($paths, $config->excludePaths);
        if ($collection->isEmpty()) {
            $io->error(sprintf('No Fluid templates found at: %s', implode(', ', $paths)));

            return Command::FAILURE;
        }

        $this->warnAboutSkipped($io, $collection);

        $threshold = max(0.1, min(1.0, $this->options->float($input, 'threshold', $config->similarThreshold, 0.8)));
        $minElements = max(2, $this->options->int($input, 'min-elements', $config->similarMinElements, 4));

        $clusters = (new NearDuplicateDetector($threshold, $minElements))->detect($collection->templates);
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonNearDuplicateReporter())->render($clusters, $fileCount));
        } else {
            (new ConsoleNearDuplicateReporter())->report($io, $clusters, $fileCount);
        }

        return $clusters === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
