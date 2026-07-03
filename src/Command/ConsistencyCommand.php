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
use YellowTwins\FluidLens\Consistency\ConsistencyCheck;
use YellowTwins\FluidLens\Consistency\ConsistencyRegistry;
use YellowTwins\FluidLens\Consistency\ConsistencyResult;
use YellowTwins\FluidLens\Report\ConsoleConsistencyReporter;
use YellowTwins\FluidLens\Report\JsonConsistencyReporter;
use YellowTwins\FluidLens\Template\TemplateCollector;

/**
 * Runs project-wide consistency checks — sliders, icon sets, … — and flags where
 * competing implementations are mixed, so they can be consolidated.
 *
 * Exits non-zero when any selected check finds a mix.
 */
#[AsCommand(
    name: 'consistency',
    description: 'Check the project for mixed slider/icon/other implementations.',
)]
final class ConsistencyCommand extends Command
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
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Run only these checks (comma-separated names).')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Skip these checks (comma-separated names).')
            ->addOption('list-checks', null, InputOption::VALUE_NONE, 'List the available checks and exit.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output findings as JSON instead of a report.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('list-checks') === true) {
            return $this->listChecks($io);
        }

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

        $results = array_map(
            fn (ConsistencyCheck $check): ConsistencyResult => $check->analyze($collection->templates),
            $this->selectChecks($input),
        );
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonConsistencyReporter())->render($results, $fileCount));
        } else {
            (new ConsoleConsistencyReporter())->report($io, $results, $fileCount);
        }

        return $this->hasInconsistency($results) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<ConsistencyCheck>
     */
    private function selectChecks(InputInterface $input): array
    {
        return ConsistencyRegistry::select(
            ConsistencyRegistry::default(),
            OptionResolver::parseCsv($input->getOption('only')),
            OptionResolver::parseCsv($input->getOption('exclude')),
        );
    }

    private function listChecks(SymfonyStyle $io): int
    {
        $io->title('Available checks');
        foreach (ConsistencyRegistry::default() as $check) {
            $io->writeln(sprintf('   %-12s <fg=gray>%s</>', $check->name(), $check->title()));
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<ConsistencyResult> $results
     */
    private function hasInconsistency(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->isInconsistent()) {
                return true;
            }
        }

        return false;
    }
}
