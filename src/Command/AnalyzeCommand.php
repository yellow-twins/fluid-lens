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
use YellowTwins\FluidLens\Config\Config;
use YellowTwins\FluidLens\Config\OptionResolver;
use YellowTwins\FluidLens\Detector\CloneDetector;
use YellowTwins\FluidLens\Detector\CloneGroup;
use YellowTwins\FluidLens\Report\ConsoleCloneReporter;
use YellowTwins\FluidLens\Report\JsonCloneReporter;
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
    use ResolvesConfig;

    private const DEFAULT_BASELINE = 'fluid-lens-baseline.json';

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
            ->addOption('min-elements', null, InputOption::VALUE_REQUIRED, 'Minimum elements per structure.')
            ->addOption('min-occurrences', null, InputOption::VALUE_REQUIRED, 'Minimum repetitions to report.')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, 'Suppress duplicates listed in this baseline.')
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

        $groups = $this->createDetector($input, $config)->detect($collection->templates);

        if ($this->isGeneratingBaseline($input)) {
            return $this->writeBaseline($io, $input, $groups);
        }

        $groups = $this->applyBaseline($io, $input, $config, $groups);
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonCloneReporter())->render($groups, $fileCount));
        } else {
            (new ConsoleCloneReporter())->report($io, $groups, $fileCount);
        }

        return $groups === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function createDetector(InputInterface $input, Config $config): CloneDetector
    {
        return new CloneDetector(
            max(1, $this->options->int($input, 'min-elements', $config->cloneMinElements, 3)),
            max(2, $this->options->int($input, 'min-occurrences', $config->cloneMinOccurrences, 2)),
        );
    }

    private function isGeneratingBaseline(InputInterface $input): bool
    {
        return $input->getOption('generate-baseline') !== false;
    }

    /**
     * @param list<CloneGroup> $groups
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
     * @param list<CloneGroup> $groups
     *
     * @return list<CloneGroup>
     */
    private function applyBaseline(SymfonyStyle $io, InputInterface $input, Config $config, array $groups): array
    {
        $path = $this->options->string($input, 'baseline', $config->baseline);
        if ($path === null) {
            return $groups;
        }

        if (!is_file($path)) {
            $io->warning(sprintf('Baseline file not found, reporting everything: %s', $path));

            return $groups;
        }

        return Baseline::fromJson((string) file_get_contents($path))->filter($groups);
    }
}
