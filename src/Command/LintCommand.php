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
use YellowTwins\FluidLens\Baseline\LintBaseline;
use YellowTwins\FluidLens\Config\Config;
use YellowTwins\FluidLens\Config\OptionResolver;
use YellowTwins\FluidLens\Report\ConsoleLintReporter;
use YellowTwins\FluidLens\Report\JsonLintReporter;
use YellowTwins\FluidLens\Report\SarifLintReporter;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Linter;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\RuleCatalog;
use YellowTwins\FluidLens\Rule\RuleSelector;
use YellowTwins\FluidLens\Rule\RuleSet;
use YellowTwins\FluidLens\Rule\Severity;
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

    private const DEFAULT_BASELINE = 'fluid-lens-lint-baseline.json';

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
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output findings as JSON instead of a report.')
            ->addOption('sarif', null, InputOption::VALUE_NONE, 'Output SARIF 2.1.0 (GitHub code scanning).')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Run only these rules (comma-separated names).')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Skip these rules (comma-separated names).')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Fail on: error, warning, notice or never.')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, 'Suppress findings listed in this baseline.')
            ->addOption(
                'generate-baseline',
                null,
                InputOption::VALUE_OPTIONAL,
                'Write the current findings to a baseline file and exit.',
                false,
            )
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

        $exclude = $this->options->stringList($input, 'exclude-path', $config->excludePaths);
        $collection = $this->collector->collectPaths($paths, $exclude);
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

        if ($this->isGeneratingBaseline($input)) {
            return $this->writeBaseline($io, $input, $findings);
        }

        $findings = $this->applyBaseline($io, $input, $config, $findings);

        match ($format) {
            'sarif' => $output->writeln((new SarifLintReporter())->render($findings)),
            'json' => $output->writeln((new JsonLintReporter())->render($findings, $fileCount)),
            default => (new ConsoleLintReporter())->report($io, $findings, $fileCount),
        };

        $failOn = $this->options->string($input, 'fail-on', $config->lintFailOn) ?? 'warning';

        return $this->hasFailure($findings, $failOn) ? Command::FAILURE : Command::SUCCESS;
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
        $groups = [];
        foreach (RuleSet::default() as $rule) {
            $groups[explode('.', $rule->name())[0]][] = $rule->name();
        }

        $io->title('Available rules');
        foreach ($groups as $group => $names) {
            $io->writeln(sprintf(' <options=bold>%s</>', $group));
            foreach ($names as $name) {
                $io->writeln(sprintf('   %-28s <fg=gray>%s</>', $name, RuleCatalog::describe($name) ?? ''));
            }
            $io->newLine();
        }

        $io->writeln(' Filter with --only / --exclude; a trailing * matches a prefix (e.g. wcag.*).');

        return Command::SUCCESS;
    }

    private function isGeneratingBaseline(InputInterface $input): bool
    {
        return $input->getOption('generate-baseline') !== false;
    }

    /**
     * @param list<Finding> $findings
     */
    private function writeBaseline(SymfonyStyle $io, InputInterface $input, array $findings): int
    {
        $value = $input->getOption('generate-baseline');
        $path = is_string($value) && $value !== '' ? $value : self::DEFAULT_BASELINE;

        if (file_put_contents($path, LintBaseline::fromFindings($findings)->toJson()) === false) {
            $io->error(sprintf('Could not write baseline to: %s', $path));

            return Command::FAILURE;
        }

        $io->success(sprintf('Wrote baseline with %d finding(s) to %s.', count($findings), $path));

        return Command::SUCCESS;
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<Finding>
     */
    private function applyBaseline(SymfonyStyle $io, InputInterface $input, Config $config, array $findings): array
    {
        $path = $this->options->string($input, 'baseline', $config->lintBaseline);
        if ($path === null) {
            return $findings;
        }

        if (!is_file($path)) {
            $io->warning(sprintf('Baseline file not found, reporting everything: %s', $path));

            return $findings;
        }

        return LintBaseline::fromJson((string) file_get_contents($path))->filter($findings);
    }

    /**
     * @param list<Finding> $findings
     */
    private function hasFailure(array $findings, string $failOn): bool
    {
        $threshold = $this->threshold($failOn);
        if ($threshold === null) {
            return false;
        }

        foreach ($findings as $finding) {
            if ($finding->severity->rank() >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * The severity rank at or above which a run fails, or null to never fail.
     */
    private function threshold(string $failOn): ?int
    {
        return match (strtolower($failOn)) {
            'error' => Severity::Error->rank(),
            'notice' => Severity::Notice->rank(),
            'never' => null,
            default => Severity::Warning->rank(),
        };
    }
}
