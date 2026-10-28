<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YellowTwins\FluidLens\Baseline\I18nBaseline;
use YellowTwins\FluidLens\Config\Config;
use YellowTwins\FluidLens\Config\OptionResolver;
use YellowTwins\FluidLens\I18n\ExtractionPlan;
use YellowTwins\FluidLens\I18n\ExtractionPlanner;
use YellowTwins\FluidLens\I18n\ExtractionSettings;
use YellowTwins\FluidLens\I18n\ExtractionWriter;
use YellowTwins\FluidLens\I18n\FinderSettings;
use YellowTwins\FluidLens\I18n\HardcodedText;
use YellowTwins\FluidLens\I18n\HardcodedTextFinder;
use YellowTwins\FluidLens\I18n\PlannedText;
use YellowTwins\FluidLens\I18n\TargetResolver;
use YellowTwins\FluidLens\I18n\TranslateStyle;
use YellowTwins\FluidLens\Report\ConsoleI18nReporter;
use YellowTwins\FluidLens\Report\JsonI18nReporter;
use YellowTwins\FluidLens\Report\SarifLintReporter;
use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\Severity;
use YellowTwins\FluidLens\Support\Paths;
use YellowTwins\FluidLens\Support\UnifiedDiff;
use YellowTwins\FluidLens\Template\TemplateCollector;

/**
 * Finds text written straight into templates and, with --fix, moves it into the
 * language files: the text stays in the language it is written in, the template
 * gets an f:translate call in its place.
 *
 * Exits non-zero while hardcoded text remains, so it can gate CI.
 */
#[AsCommand(
    name: 'i18n',
    description: 'Find hardcoded text in Fluid templates and move it into XLIFF language files.',
)]
final class I18nCommand extends Command
{
    use ResolvesConfig;

    private const DEFAULT_BASELINE = 'fluid-lens-i18n-baseline.json';

    private const RULE = 'i18n.hardcoded-text';

    public function __construct(
        private readonly TemplateCollector $collector = new TemplateCollector(),
        private readonly OptionResolver $options = new OptionResolver(),
        private readonly ExtractionWriter $writer = new ExtractionWriter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'A file or directory (default: config paths).')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to a fluid-lens.php config file.')
            ->addOption('exclude-path', null, InputOption::VALUE_REQUIRED, 'Glob patterns of files to skip.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output the texts as JSON instead of a report.')
            ->addOption('sarif', null, InputOption::VALUE_NONE, 'Output SARIF 2.1.0 (GitHub code scanning).')
            ->addOption('min-letters', null, InputOption::VALUE_REQUIRED, 'Letters a text needs to count (default 2).')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Move the texts into the language files.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'With --fix: print a diff instead of writing.')
            ->addOption('style', null, InputOption::VALUE_REQUIRED, 'Text content as "tag" or "inline" f:translate.')
            ->addOption(
                'language',
                null,
                InputOption::VALUE_REQUIRED,
                'Language the templates are written in; also writes e.g. de.locallang.xlf.',
            )
            ->addOption('source-language', null, InputOption::VALUE_REQUIRED, 'source-language of new files (en).')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Language file for templates outside an extension.')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, 'Accept the texts listed in this baseline.')
            ->addOption(
                'generate-baseline',
                null,
                InputOption::VALUE_OPTIONAL,
                'Write the current texts to a baseline file and exit.',
                false,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->loadConfig($input);

        $style = $this->resolveStyle($input, $config);
        if ($style === false) {
            $io->error('--style must be "tag" or "inline".');

            return Command::FAILURE;
        }

        $paths = $this->resolvePaths($input, $config);
        if ($paths === []) {
            $io->error('No path given. Pass one or set "paths" in fluid-lens.php.');

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

        $finder = new HardcodedTextFinder($this->finderSettings($input, $config));
        $sources = [];
        $texts = [];
        foreach ($collection->templates as $template) {
            $sources[$template->file] = $template->source;
            array_push($texts, ...$finder->find($template->file, $template->source));
        }

        if ($this->isGeneratingBaseline($input)) {
            return $this->writeBaseline($io, $input, $texts);
        }
        $texts = $this->applyBaseline($io, $input, $config, $texts);

        $planner = new ExtractionPlanner(
            new TargetResolver($this->options->string($input, 'file', $config->i18n->file)),
            $finder,
            new ExtractionSettings(
                $style,
                $this->options->string($input, 'language', $config->i18n->language),
                $this->options->string($input, 'source-language', $config->i18n->sourceLanguage)
                    ?? ExtractionSettings::DEFAULT_SOURCE_LANGUAGE,
            ),
        );
        $plan = $planner->plan($texts, $sources);
        $fileCount = count($collection->templates);

        if ($input->getOption('fix') === true) {
            return $input->getOption('dry-run') === true
                ? $this->printDiff($output, $plan)
                : $this->apply($io, $plan, $fileCount);
        }

        match ($format) {
            'sarif' => $output->writeln((new SarifLintReporter())->render($this->asFindings($plan))),
            'json' => $output->writeln((new JsonI18nReporter())->render($plan, $fileCount)),
            default => (new ConsoleI18nReporter())->report($io, $plan, $fileCount),
        };

        return $plan->texts === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function resolveFormat(InputInterface $input): string
    {
        if ($input->getOption('sarif') === true) {
            return 'sarif';
        }

        return $input->getOption('json') === true ? 'json' : 'console';
    }

    /**
     * @return TranslateStyle|false|null null to follow the templates, false when invalid
     */
    private function resolveStyle(InputInterface $input, Config $config): TranslateStyle|false|null
    {
        $value = $this->options->string($input, 'style', $config->i18n->style);

        return $value === null ? null : (TranslateStyle::tryFrom(strtolower($value)) ?? false);
    }

    private function finderSettings(InputInterface $input, Config $config): FinderSettings
    {
        return new FinderSettings(
            $this->options->int($input, 'min-letters', $config->i18n->minLetters, FinderSettings::DEFAULT_MIN_LETTERS),
            $config->i18n->ignore,
            array_map('strtolower', $config->i18n->attributes),
        );
    }

    private function printDiff(OutputInterface $output, ExtractionPlan $plan): int
    {
        $diff = new UnifiedDiff();
        foreach ($plan->changes as $change) {
            $output->write(
                $diff->render(Paths::relative($change->path), $change->before, $change->after),
                false,
                OutputInterface::OUTPUT_RAW,
            );
        }

        foreach ($plan->manual() as $text) {
            $output->writeln(
                sprintf('# manual: %s:%d %s', Paths::relative($text->text->file), $text->text->line, $text->blocker()),
                OutputInterface::OUTPUT_RAW,
            );
        }

        return $plan->texts === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function apply(SymfonyStyle $io, ExtractionPlan $plan, int $fileCount): int
    {
        $failed = $this->writer->write($plan->changes);
        foreach ($failed as $path) {
            $io->error(sprintf('Could not write %s', $path));
        }

        $written = count($plan->changes) - count($failed);
        if ($written > 0) {
            $io->success(sprintf(
                'Moved %d text(s) into language files; %d file(s) written.',
                count($plan->extractable()),
                $written,
            ));
            foreach ($plan->changes as $change) {
                if (!in_array($change->path, $failed, true)) {
                    $io->writeln(sprintf(
                        '  %s %s',
                        $change->isNew() ? '<fg=green>created</>' : '<fg=yellow>updated</>',
                        OutputFormatter::escape(Paths::relative($change->path)),
                    ));
                }
            }
            $io->newLine();
        }

        $remaining = new ExtractionPlan($plan->manual(), []);
        (new ConsoleI18nReporter())->report($io, $remaining, $fileCount);

        return $failed === [] && $remaining->texts === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return list<Finding>
     */
    private function asFindings(ExtractionPlan $plan): array
    {
        return array_map(static function (PlannedText $text): Finding {
            $message = $text->isExtractable()
                ? sprintf('Hardcoded text "%s"; move it to the key %s.', $text->text->text, (string) $text->key)
                : sprintf('Hardcoded text "%s": %s.', $text->text->text, (string) $text->blocker());

            return new Finding(self::RULE, Severity::Warning, $message, $text->text->file, $text->text->line);
        }, $plan->texts);
    }

    private function isGeneratingBaseline(InputInterface $input): bool
    {
        return $input->getOption('generate-baseline') !== false;
    }

    /**
     * @param list<HardcodedText> $texts
     */
    private function writeBaseline(SymfonyStyle $io, InputInterface $input, array $texts): int
    {
        $value = $input->getOption('generate-baseline');
        $path = is_string($value) && $value !== '' ? $value : self::DEFAULT_BASELINE;

        if (file_put_contents($path, I18nBaseline::fromTexts($texts)->toJson()) === false) {
            $io->error(sprintf('Could not write baseline to: %s', $path));

            return Command::FAILURE;
        }

        $io->success(sprintf('Wrote baseline with %d text(s) to %s.', count($texts), $path));

        return Command::SUCCESS;
    }

    /**
     * @param list<HardcodedText> $texts
     *
     * @return list<HardcodedText>
     */
    private function applyBaseline(SymfonyStyle $io, InputInterface $input, Config $config, array $texts): array
    {
        $path = $this->options->string($input, 'baseline', $config->i18n->baseline);
        if ($path === null) {
            return $texts;
        }

        if (!is_file($path)) {
            $io->warning(sprintf('Baseline file not found, reporting everything: %s', $path));

            return $texts;
        }

        return I18nBaseline::fromJson((string) file_get_contents($path))->filter($texts);
    }
}
