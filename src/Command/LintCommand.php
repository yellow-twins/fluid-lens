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
use YellowTwins\FluidLens\Report\ConsoleLintReporter;
use YellowTwins\FluidLens\Report\JsonLintReporter;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Linter;
use YellowTwins\FluidLens\Rule\Rule;
use YellowTwins\FluidLens\Rule\RuleSelector;
use YellowTwins\FluidLens\Rule\RuleSet;
use YellowTwins\FluidLens\Template\TemplateCollection;
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
    public function __construct(
        private readonly TemplateCollector $collector = new TemplateCollector(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'A template file or a directory to scan recursively.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output findings as JSON instead of a report.')
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

        $path = $input->getArgument('path');
        if (!is_string($path)) {
            $io->error('Please provide a path to analyse (or use --list-rules).');

            return Command::FAILURE;
        }

        $collection = $this->collector->collect($path);

        if ($collection->isEmpty()) {
            $io->error(sprintf('No Fluid templates found at: %s', $path));

            return Command::FAILURE;
        }

        $this->warnAboutSkipped($io, $collection);

        $findings = (new Linter($this->selectRules($input)))->lint($collection->templates);
        $fileCount = count($collection->templates);

        if ($input->getOption('json') === true) {
            $output->writeln((new JsonLintReporter())->render($findings, $fileCount));
        } else {
            (new ConsoleLintReporter())->report($io, $findings, $fileCount);
        }

        return $this->hasBuildFailure($findings) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<Rule>
     */
    private function selectRules(InputInterface $input): array
    {
        return (new RuleSelector())->select(
            RuleSet::default(),
            $this->parseList($input->getOption('only')),
            $this->parseList($input->getOption('exclude')),
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
     * @return list<string>
     */
    private function parseList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $names = array_map('trim', explode(',', $value));

        return array_values(array_filter($names, static fn (string $name): bool => $name !== ''));
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

    private function warnAboutSkipped(SymfonyStyle $io, TemplateCollection $collection): void
    {
        foreach ($collection->skipped as $skip) {
            $io->warning(sprintf('Skipped %s: %s', $skip['file'], $skip['reason']));
        }
    }
}
