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
use YellowTwins\FluidLens\Consistency\SliderLibraryDetector;
use YellowTwins\FluidLens\Report\ConsoleSliderReporter;
use YellowTwins\FluidLens\Report\JsonSliderReporter;
use YellowTwins\FluidLens\Template\TemplateCollector;

/**
 * Reports which slider/carousel libraries the templates use, and flags a project
 * that mixes several — a cue to consolidate on one.
 *
 * Exits non-zero when more than one library is found.
 */
#[AsCommand(
    name: 'sliders',
    description: 'Report slider/carousel libraries used and flag inconsistent use.',
)]
final class SlidersCommand extends Command
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
            ->addOption('exclude-path', null, InputOption::VALUE_REQUIRED, 'Glob patterns of files to skip.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output findings as JSON instead of a report.');
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

        $exclude = $this->options->stringList($input, 'exclude-path', $config->excludePaths);
        $collection = $this->collector->collectPaths($paths, $exclude);
        if ($collection->isEmpty()) {
            $io->error(sprintf('No Fluid templates found at: %s', implode(', ', $paths)));

            return Command::FAILURE;
        }

        $usages = (new SliderLibraryDetector())->detect($collection->templates);
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonSliderReporter())->render($usages, $fileCount));
        } else {
            (new ConsoleSliderReporter())->report($io, $usages, $fileCount);
        }

        return count($usages) > 1 ? Command::FAILURE : Command::SUCCESS;
    }
}
