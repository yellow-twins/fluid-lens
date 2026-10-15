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
use YellowTwins\FluidLens\Component\AdvisorSettings;
use YellowTwins\FluidLens\Component\ComponentAdvisor;
use YellowTwins\FluidLens\Component\Model\CandidateSource;
use YellowTwins\FluidLens\Config\Config;
use YellowTwins\FluidLens\Config\OptionResolver;
use YellowTwins\FluidLens\Report\ConsoleComponentReporter;
use YellowTwins\FluidLens\Report\JsonComponentReporter;
use YellowTwins\FluidLens\Template\TemplateCollector;

/**
 * Suggests which Partials and repeated markup should become Fluid components
 * (TYPO3 14 / Fluid 5), with proposed arguments, a draft and the replacing call
 * for each occurrence. Nothing is written.
 *
 * Exits non-zero when there are suggestions, like the other analysers.
 */
#[AsCommand(
    name: 'components',
    description: 'Suggest Fluid components (Fluid 5 / TYPO3 14) for repeated markup and Partials.',
)]
final class ComponentsCommand extends Command
{
    use ResolvesConfig;

    private const SOURCES = [
        'partials' => CandidateSource::Partial,
        'clones' => CandidateSource::Clone,
        'similar' => CandidateSource::NearDuplicate,
    ];

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
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output the suggestions as JSON.')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Signals to use: partials, clones, similar.')
            ->addOption('min-occurrences', null, InputOption::VALUE_REQUIRED, 'Call sites a candidate needs (2).')
            ->addOption('min-files', null, InputOption::VALUE_REQUIRED, 'Files the call sites must span (2).')
            ->addOption('min-elements', null, InputOption::VALUE_REQUIRED, 'Minimum size of copied markup (3).')
            ->addOption('max-arguments', null, InputOption::VALUE_REQUIRED, 'Flag suggestions above this (6).')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Similarity for similar markup (0.8).')
            ->addOption('min-shared', null, InputOption::VALUE_REQUIRED, 'Content copies must share (0.45).')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Show at most this many candidates.', '20')
            ->addOption('no-draft', null, InputOption::VALUE_NONE, 'Leave out the drafts in the report.');
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

        $sources = $this->sources($input, $config);
        if ($sources === null) {
            $io->error('Unknown signal in --only. Use: ' . implode(', ', array_keys(self::SOURCES)) . '.');

            return Command::FAILURE;
        }

        $exclude = $this->options->stringList($input, 'exclude-path', $config->excludePaths);
        $collection = $this->collector->collectPaths($paths, $exclude);
        if ($collection->isEmpty()) {
            $io->error(sprintf('No Fluid templates found at: %s', implode(', ', $paths)));

            return Command::FAILURE;
        }

        $this->warnAboutSkipped($io, $collection);

        $report = (new ComponentAdvisor($this->settings($input, $config, $sources)))->advise($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonComponentReporter())->render($report));
        } else {
            $limit = max(1, (int) $input->getOption('limit'));
            (new ConsoleComponentReporter($limit, $input->getOption('no-draft') !== true))->report($io, $report);
        }

        return $report->candidates === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<CandidateSource> $sources
     */
    private function settings(InputInterface $input, Config $config, array $sources): AdvisorSettings
    {
        $components = $config->components;

        return new AdvisorSettings(
            minOccurrences: max(2, $this->options->int($input, 'min-occurrences', $components->minOccurrences, 2)),
            minFiles: max(1, $this->options->int($input, 'min-files', $components->minFiles, 2)),
            minElements: max(1, $this->options->int($input, 'min-elements', $components->minElements, 3)),
            maxArguments: max(1, $this->options->int($input, 'max-arguments', $components->maxArguments, 6)),
            threshold: $this->options->float($input, 'threshold', $components->threshold, 0.8),
            sources: $sources,
            prefixes: $components->prefixes,
            minShared: $this->options->float($input, 'min-shared', $components->minShared, 0.45),
        );
    }

    /**
     * @return list<CandidateSource>|null null when a name is unknown
     */
    private function sources(InputInterface $input, Config $config): ?array
    {
        $names = $this->options->stringList($input, 'only', $config->components->only);
        if ($names === []) {
            return array_values(self::SOURCES);
        }

        $sources = [];
        foreach ($names as $name) {
            $source = self::SOURCES[$name] ?? null;
            if ($source === null) {
                return null;
            }
            $sources[] = $source;
        }

        return $sources;
    }
}
