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
use YellowTwins\FluidLens\Config\Config;
use YellowTwins\FluidLens\Config\OptionResolver;
use YellowTwins\FluidLens\Report\ConsoleLintReporter;
use YellowTwins\FluidLens\Report\JsonLintReporter;
use YellowTwins\FluidLens\Report\SarifLintReporter;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Linter;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\RuleSelector;
use YellowTwins\FluidLens\Rule\RuleSet;
use YellowTwins\FluidLens\Template\TemplateCollector;

/**
 * Lints Fluid templates for accessibility (WCAG) and best-practice problems.
 *
 * Exits non-zero when any error or warning is found, so it can gate CI; notices
 * are advisory and never fail the build.
 */
#[AsCommand(
    name: 'lint',
    description: 'Check Fluid templates for accessibility (WCAG) and best-practice issues.',
)]
final class LintCommand extends Command
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
            ->addOption('sarif', null, InputOption::VALUE_NONE, 'Output SARIF 2.1.0 (GitHub code scanning).')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Run only these rules (comma-separated names).')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Skip these rules (comma-separated names).')
            ->addOption('list-rules', null, InputOption::VALUE_NONE, 'List the available rules and exit.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('list-rules') === true) {
            return $this->listRules($io);
        }

        $config = $this->loadConfig($input);

        $paths = $this->resolvePaths($input, $config);
        if ($paths === []) {
            $io->error('No path given. Pass one, use --list-rules, or set "paths" in fluid-lens.php.');

            return Command::FAILURE;
        }

        $collection = $this->collector->collectPaths($paths);
        if ($collection->isEmpty()) {
            $io->error(sprintf('No Fluid templates found at: %s', implode(', ', $paths)));

            return Command::FAILURE;
        }

        $format = $this->resolveFormat($input);
        if ($format === 'console') {
            $this->warnAboutSkipped($io, $collection);
        }

        $findings = (new Linter($this->selectRules($input, $config)))->lint($collection->templates);
        $fileCount = count($collection->templates);

        match ($format) {
            'sarif' => $output->writeln((new SarifLintReporter())->render($findings)),
            'json' => $output->writeln((new JsonLintReporter())->render($findings, $fileCount)),
            default => (new ConsoleLintReporter())->report($io, $findings, $fileCount),
        };

        return $this->hasBuildFailure($findings) ? Command::FAILURE : Command::SUCCESS;
    }

    private function resolveFormat(InputInterface $input): string
    {
        if ($input->getOption('sarif') === true) {
            return 'sarif';
        }

        return $input->getOption('json') === true ? 'json' : 'console';
    }

    /**
     * @return list<Rule>
     */
    private function selectRules(InputInterface $input, Config $config): array
    {
        return (new RuleSelector())->select(
            RuleSet::default(),
            $this->options->stringList($input, 'only', $config->lintOnly),
            $this->options->stringList($input, 'exclude', $config->lintExclude),
        );
    }

    private function listRules(SymfonyStyle $io): int
    {
        $io->title('Available rules');
        foreach (RuleSet::default() as $rule) {
            $io->writeln(' ' . $rule->name());
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<Finding> $findings
     */
    private function hasBuildFailure(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding->severity->failsBuild()) {
                return true;
            }
        }

        return false;
    }
}
